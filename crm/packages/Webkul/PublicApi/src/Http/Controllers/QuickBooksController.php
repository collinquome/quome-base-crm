<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class QuickBooksController extends Controller
{
    /**
     * Get QuickBooks connection status.
     */
    public function status(): JsonResponse
    {
        $config = DB::table('integrations')
            ->where('provider', 'quickbooks')
            ->first();

        if (! $config) {
            return response()->json([
                'data' => ['connected' => false],
            ]);
        }

        $settings = json_decode($config->settings, true) ?? [];

        return response()->json([
            'data' => [
                'connected'    => (bool) $config->active,
                'company_id'   => $settings['realm_id'] ?? null,
                'connected_at' => $config->created_at,
            ],
        ]);
    }

    /**
     * Get OAuth2 authorization URL.
     */
    public function authUrl(Request $request): JsonResponse
    {
        $request->validate([
            'client_id'    => 'required|string',
            'client_secret' => 'required|string',
            'redirect_uri' => 'required|url',
        ]);

        // Store client credentials temporarily
        DB::table('integrations')->updateOrInsert(
            ['provider' => 'quickbooks'],
            [
                'active'   => false,
                'settings' => json_encode([
                    'client_id'     => $request->input('client_id'),
                    'client_secret' => $request->input('client_secret'),
                    'redirect_uri'  => $request->input('redirect_uri'),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $params = http_build_query([
            'client_id'     => $request->input('client_id'),
            'response_type' => 'code',
            'scope'         => 'com.intuit.quickbooks.accounting',
            'redirect_uri'  => $request->input('redirect_uri'),
            'state'         => csrf_token(),
        ]);

        return response()->json([
            'data' => [
                'auth_url' => "https://appcenter.intuit.com/connect/oauth2?{$params}",
            ],
        ]);
    }

    /**
     * Handle OAuth2 callback / token exchange.
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code'     => 'required|string',
            'realm_id' => 'required|string',
        ]);

        $config = DB::table('integrations')
            ->where('provider', 'quickbooks')
            ->first();

        if (! $config) {
            return response()->json(['message' => 'QuickBooks not initialized'], 422);
        }

        $settings = json_decode($config->settings, true) ?? [];
        $clientId = $settings['client_id'] ?? '';
        $clientSecret = $settings['client_secret'] ?? '';
        $redirectUri = $settings['redirect_uri'] ?? '';

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post('https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer', [
                'grant_type'   => 'authorization_code',
                'code'         => $request->input('code'),
                'redirect_uri' => $redirectUri,
            ]);

        if (! $response->ok()) {
            return response()->json(['message' => 'Token exchange failed', 'error' => $response->json()], 502);
        }

        $tokens = $response->json();

        DB::table('integrations')->where('provider', 'quickbooks')->update([
            'active'   => true,
            'settings' => json_encode(array_merge($settings, [
                'realm_id'      => $request->input('realm_id'),
                'access_token'  => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at'    => now()->addSeconds($tokens['expires_in'] ?? 3600)->toIso8601String(),
            ])),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data'    => ['connected' => true, 'company_id' => $request->input('realm_id')],
            'message' => 'QuickBooks connected successfully.',
        ]);
    }

    /**
     * Disconnect QuickBooks.
     */
    public function disconnect(): JsonResponse
    {
        DB::table('integrations')
            ->where('provider', 'quickbooks')
            ->delete();

        return response()->json(['message' => 'QuickBooks disconnected.']);
    }

    /**
     * Create an invoice from a CRM lead/deal.
     */
    public function createInvoice(Request $request): JsonResponse
    {
        $request->validate([
            'contact_id'  => 'required|integer',
            'line_items'  => 'required|array|min:1',
            'line_items.*.description' => 'required|string',
            'line_items.*.amount'      => 'required|numeric|min:0',
            'line_items.*.quantity'     => 'sometimes|integer|min:1',
            'due_date'    => 'sometimes|date',
        ]);

        [$settings, $error] = $this->getActiveConfig();

        if ($error) {
            return $error;
        }

        $contact = DB::table('persons')->where('id', $request->input('contact_id'))->first();

        if (! $contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        // Build QuickBooks invoice payload
        $lineItems = [];
        foreach ($request->input('line_items') as $item) {
            $lineItems[] = [
                'DetailType' => 'SalesItemLineDetail',
                'Amount'     => ($item['amount'] ?? 0) * ($item['quantity'] ?? 1),
                'Description' => $item['description'],
                'SalesItemLineDetail' => [
                    'UnitPrice' => $item['amount'],
                    'Qty'       => $item['quantity'] ?? 1,
                ],
            ];
        }

        $invoiceData = [
            'Line' => $lineItems,
            'CustomerRef' => [
                'value' => $this->findOrCreateQbCustomer($settings, $contact),
            ],
        ];

        if ($request->has('due_date')) {
            $invoiceData['DueDate'] = $request->input('due_date');
        }

        $response = $this->qbRequest($settings, 'POST', '/invoice', $invoiceData);

        if (! $response->ok()) {
            return response()->json([
                'message' => 'Failed to create invoice in QuickBooks',
                'error'   => $response->json(),
            ], 502);
        }

        $invoice = $response->json('Invoice') ?? $response->json();

        // Store sync record
        DB::table('quickbooks_syncs')->insert([
            'contact_id'    => $contact->id,
            'qb_type'       => 'invoice',
            'qb_id'         => $invoice['Id'] ?? null,
            'qb_doc_number' => $invoice['DocNumber'] ?? null,
            'amount'        => $invoice['TotalAmt'] ?? 0,
            'status'        => 'created',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'data' => [
                'invoice_id'  => $invoice['Id'] ?? null,
                'doc_number'  => $invoice['DocNumber'] ?? null,
                'total'       => $invoice['TotalAmt'] ?? 0,
                'contact_id'  => $contact->id,
            ],
            'message' => 'Invoice created in QuickBooks.',
        ], 201);
    }

    /**
     * List QuickBooks sync records for a contact.
     */
    public function contactSyncs(int $contactId): JsonResponse
    {
        $syncs = DB::table('quickbooks_syncs')
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $syncs]);
    }

    /**
     * Sync a CRM contact to QuickBooks as a customer.
     */
    public function syncCustomer(Request $request): JsonResponse
    {
        $request->validate([
            'contact_id' => 'required|integer',
        ]);

        [$settings, $error] = $this->getActiveConfig();

        if ($error) {
            return $error;
        }

        $contact = DB::table('persons')->where('id', $request->input('contact_id'))->first();

        if (! $contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $emails = json_decode($contact->emails, true) ?? [];
        $email = $emails[0]['value'] ?? null;

        $customerData = [
            'DisplayName'        => $contact->name,
            'PrimaryEmailAddr'   => $email ? ['Address' => $email] : null,
            'CompanyName'        => $contact->name,
        ];

        $response = $this->qbRequest($settings, 'POST', '/customer', array_filter($customerData));

        if (! $response->ok()) {
            return response()->json([
                'message' => 'Failed to sync customer to QuickBooks',
                'error'   => $response->json(),
            ], 502);
        }

        $customer = $response->json('Customer') ?? $response->json();

        DB::table('quickbooks_syncs')->insert([
            'contact_id'    => $contact->id,
            'qb_type'       => 'customer',
            'qb_id'         => $customer['Id'] ?? null,
            'status'        => 'synced',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'data' => [
                'contact_id'  => $contact->id,
                'customer_id' => $customer['Id'] ?? null,
            ],
            'message' => 'Customer synced to QuickBooks.',
        ], 201);
    }

    /**
     * Make a QuickBooks API request.
     */
    private function qbRequest(array $settings, string $method, string $endpoint, array $data = [])
    {
        $baseUrl = 'https://quickbooks.api.intuit.com/v3/company/' . ($settings['realm_id'] ?? '');

        return Http::withToken($settings['access_token'] ?? '')
            ->withHeaders(['Accept' => 'application/json'])
            ->{strtolower($method)}($baseUrl . $endpoint, $data);
    }

    /**
     * Find or create a QuickBooks customer for a contact.
     */
    private function findOrCreateQbCustomer(array $settings, object $contact): string
    {
        // Check if we already have a QB customer ID
        $existing = DB::table('quickbooks_syncs')
            ->where('contact_id', $contact->id)
            ->where('qb_type', 'customer')
            ->first();

        if ($existing && $existing->qb_id) {
            return $existing->qb_id;
        }

        // Create a new customer
        $response = $this->qbRequest($settings, 'POST', '/customer', [
            'DisplayName' => $contact->name . ' (CRM-' . $contact->id . ')',
        ]);

        if ($response->ok()) {
            $customer = $response->json('Customer') ?? [];
            $qbId = $customer['Id'] ?? '1';

            DB::table('quickbooks_syncs')->insert([
                'contact_id' => $contact->id,
                'qb_type'    => 'customer',
                'qb_id'      => $qbId,
                'status'     => 'synced',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $qbId;
        }

        return '1'; // fallback
    }

    /**
     * Get active QuickBooks config.
     */
    private function getActiveConfig(): array
    {
        $config = DB::table('integrations')
            ->where('provider', 'quickbooks')
            ->where('active', true)
            ->first();

        if (! $config) {
            return [null, response()->json(['message' => 'QuickBooks not connected'], 422)];
        }

        return [json_decode($config->settings, true) ?? [], null];
    }
}
