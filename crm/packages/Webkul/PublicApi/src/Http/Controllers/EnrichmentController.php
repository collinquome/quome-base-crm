<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EnrichmentController extends Controller
{
    /**
     * Get enrichment provider configuration.
     */
    public function config(): JsonResponse
    {
        $config = DB::table('integrations')
            ->where('provider', 'enrichment')
            ->first();

        if (! $config) {
            return response()->json([
                'data' => [
                    'configured' => false,
                    'provider'   => null,
                ],
            ]);
        }

        $settings = json_decode($config->settings, true) ?? [];

        return response()->json([
            'data' => [
                'configured' => (bool) $config->active,
                'provider'   => $settings['provider'] ?? null,
            ],
        ]);
    }

    /**
     * Configure enrichment provider.
     */
    public function configure(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:clearbit,hunter,apollo,manual',
            'api_key'  => 'required_unless:provider,manual|string',
        ]);

        $provider = $request->input('provider');
        $apiKey = $request->input('api_key');

        // Verify the API key if not manual mode
        if ($provider !== 'manual' && $apiKey) {
            $valid = $this->verifyApiKey($provider, $apiKey);

            if (! $valid) {
                return response()->json(['message' => "Invalid {$provider} API key"], 422);
            }
        }

        DB::table('integrations')->updateOrInsert(
            ['provider' => 'enrichment'],
            [
                'active'   => true,
                'settings' => json_encode([
                    'provider' => $provider,
                    'api_key'  => $apiKey,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'data'    => ['configured' => true, 'provider' => $provider],
            'message' => "Enrichment provider ({$provider}) configured.",
        ]);
    }

    /**
     * Enrich a contact by ID.
     */
    public function enrich(Request $request, int $contactId): JsonResponse
    {
        $contact = DB::table('persons')->where('id', $contactId)->first();

        if (! $contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $emails = json_decode($contact->emails, true) ?? [];
        $email = $emails[0]['value'] ?? null;

        if (! $email) {
            return response()->json(['message' => 'Contact has no email address to enrich from'], 422);
        }

        $config = DB::table('integrations')
            ->where('provider', 'enrichment')
            ->where('active', true)
            ->first();

        $settings = $config ? json_decode($config->settings, true) ?? [] : [];
        $provider = $settings['provider'] ?? 'manual';
        $apiKey = $settings['api_key'] ?? null;

        $enriched = $this->fetchEnrichmentData($provider, $apiKey, $email, $contact->name);

        // Store enrichment result
        $existing = DB::table('enrichment_results')->where('contact_id', $contactId)->first();

        if ($existing) {
            DB::table('enrichment_results')->where('contact_id', $contactId)->update([
                'provider'    => $provider,
                'email'       => $email,
                'data'        => json_encode($enriched),
                'enriched_at' => now(),
                'updated_at'  => now(),
            ]);
        } else {
            DB::table('enrichment_results')->insert([
                'contact_id'  => $contactId,
                'provider'    => $provider,
                'email'       => $email,
                'data'        => json_encode($enriched),
                'enriched_at' => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return response()->json([
            'data' => [
                'contact_id' => $contactId,
                'provider'   => $provider,
                'enriched'   => $enriched,
            ],
            'message' => 'Contact enriched.',
        ]);
    }

    /**
     * Get enrichment data for a contact.
     */
    public function show(int $contactId): JsonResponse
    {
        $result = DB::table('enrichment_results')
            ->where('contact_id', $contactId)
            ->first();

        if (! $result) {
            return response()->json([
                'data' => ['enriched' => false, 'contact_id' => $contactId],
            ]);
        }

        return response()->json([
            'data' => [
                'enriched'    => true,
                'contact_id'  => $contactId,
                'provider'    => $result->provider,
                'data'        => json_decode($result->data, true),
                'enriched_at' => $result->enriched_at,
            ],
        ]);
    }

    /**
     * Bulk enrich multiple contacts.
     */
    public function bulkEnrich(Request $request): JsonResponse
    {
        $request->validate([
            'contact_ids' => 'required|array|min:1|max:50',
            'contact_ids.*' => 'integer',
        ]);

        $results = [];
        $contactIds = $request->input('contact_ids');

        foreach ($contactIds as $id) {
            $contact = DB::table('persons')->where('id', $id)->first();

            if (! $contact) {
                $results[] = ['contact_id' => $id, 'status' => 'not_found'];
                continue;
            }

            $emails = json_decode($contact->emails, true) ?? [];
            $email = $emails[0]['value'] ?? null;

            if (! $email) {
                $results[] = ['contact_id' => $id, 'status' => 'no_email'];
                continue;
            }

            $config = DB::table('integrations')
                ->where('provider', 'enrichment')
                ->where('active', true)
                ->first();

            $settings = $config ? json_decode($config->settings, true) ?? [] : [];
            $provider = $settings['provider'] ?? 'manual';
            $apiKey = $settings['api_key'] ?? null;

            $enriched = $this->fetchEnrichmentData($provider, $apiKey, $email, $contact->name);

            $existingResult = DB::table('enrichment_results')->where('contact_id', $id)->first();

            if ($existingResult) {
                DB::table('enrichment_results')->where('contact_id', $id)->update([
                    'provider'    => $provider,
                    'email'       => $email,
                    'data'        => json_encode($enriched),
                    'enriched_at' => now(),
                    'updated_at'  => now(),
                ]);
            } else {
                DB::table('enrichment_results')->insert([
                    'contact_id'  => $id,
                    'provider'    => $provider,
                    'email'       => $email,
                    'data'        => json_encode($enriched),
                    'enriched_at' => now(),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            $results[] = ['contact_id' => $id, 'status' => 'enriched', 'data' => $enriched];
        }

        return response()->json([
            'data'    => $results,
            'message' => count($results) . ' contacts processed.',
        ]);
    }

    /**
     * Fetch enrichment data from provider.
     */
    private function fetchEnrichmentData(string $provider, ?string $apiKey, string $email, string $name): array
    {
        switch ($provider) {
            case 'clearbit':
                return $this->enrichFromClearbit($apiKey, $email);
            case 'hunter':
                return $this->enrichFromHunter($apiKey, $email);
            case 'apollo':
                return $this->enrichFromApollo($apiKey, $email);
            default:
                return $this->enrichManual($email, $name);
        }
    }

    /**
     * Enrich from Clearbit API.
     */
    private function enrichFromClearbit(string $apiKey, string $email): array
    {
        $response = Http::withToken($apiKey)
            ->get('https://person.clearbit.com/v2/combined/find', [
                'email' => $email,
            ]);

        if (! $response->ok()) {
            return ['source' => 'clearbit', 'status' => 'not_found'];
        }

        $data = $response->json();
        $person = $data['person'] ?? [];
        $company = $data['company'] ?? [];

        return [
            'source'        => 'clearbit',
            'status'        => 'found',
            'job_title'     => $person['employment']['title'] ?? null,
            'company'       => $company['name'] ?? $person['employment']['name'] ?? null,
            'company_domain' => $company['domain'] ?? null,
            'industry'      => $company['category']['industry'] ?? null,
            'location'      => $person['geo']['city'] ?? null,
            'bio'           => $person['bio'] ?? null,
            'avatar'        => $person['avatar'] ?? null,
            'social'        => [
                'linkedin' => $person['linkedin']['handle'] ?? null,
                'twitter'  => $person['twitter']['handle'] ?? null,
                'github'   => $person['github']['handle'] ?? null,
            ],
            'company_size'  => $company['metrics']['employees'] ?? null,
            'company_revenue' => $company['metrics']['annualRevenue'] ?? null,
        ];
    }

    /**
     * Enrich from Hunter.io API.
     */
    private function enrichFromHunter(string $apiKey, string $email): array
    {
        $response = Http::get('https://api.hunter.io/v2/email-finder', [
            'email'   => $email,
            'api_key' => $apiKey,
        ]);

        if (! $response->ok()) {
            return ['source' => 'hunter', 'status' => 'not_found'];
        }

        $data = $response->json('data') ?? [];

        return [
            'source'       => 'hunter',
            'status'       => 'found',
            'first_name'   => $data['first_name'] ?? null,
            'last_name'    => $data['last_name'] ?? null,
            'company'      => $data['company'] ?? null,
            'job_title'    => $data['position'] ?? null,
            'linkedin_url' => $data['linkedin'] ?? null,
            'twitter'      => $data['twitter'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
        ];
    }

    /**
     * Enrich from Apollo.io API.
     */
    private function enrichFromApollo(string $apiKey, string $email): array
    {
        $response = Http::withHeaders(['x-api-key' => $apiKey])
            ->post('https://api.apollo.io/v1/people/match', [
                'email' => $email,
            ]);

        if (! $response->ok()) {
            return ['source' => 'apollo', 'status' => 'not_found'];
        }

        $person = $response->json('person') ?? [];

        return [
            'source'       => 'apollo',
            'status'       => 'found',
            'job_title'    => $person['title'] ?? null,
            'company'      => $person['organization_name'] ?? null,
            'linkedin_url' => $person['linkedin_url'] ?? null,
            'location'     => $person['city'] ?? null,
            'industry'     => $person['organization']?->industry ?? null,
        ];
    }

    /**
     * Manual enrichment - derive what we can from the email domain.
     */
    private function enrichManual(string $email, string $name): array
    {
        $domain = substr($email, strpos($email, '@') + 1);

        // Skip free email providers
        $freeProviders = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'icloud.com', 'protonmail.com', 'mail.com'];

        $company = null;
        $companyDomain = null;

        if (! in_array($domain, $freeProviders)) {
            $company = ucwords(str_replace(['.com', '.co', '.io', '.org', '.net'], '', explode('.', $domain)[0]));
            $companyDomain = $domain;
        }

        return [
            'source'         => 'manual',
            'status'         => 'inferred',
            'company'        => $company,
            'company_domain' => $companyDomain,
            'email_domain'   => $domain,
        ];
    }

    /**
     * Verify an API key with the provider.
     */
    private function verifyApiKey(string $provider, string $apiKey): bool
    {
        try {
            switch ($provider) {
                case 'clearbit':
                    $res = Http::withToken($apiKey)->get('https://person.clearbit.com/v2/combined/find', ['email' => 'test@test.com']);

                    return $res->status() !== 401;
                case 'hunter':
                    $res = Http::get('https://api.hunter.io/v2/account', ['api_key' => $apiKey]);

                    return $res->ok();
                case 'apollo':
                    $res = Http::withHeaders(['x-api-key' => $apiKey])->get('https://api.apollo.io/v1/auth/health');

                    return $res->ok();
                default:
                    return true;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}
