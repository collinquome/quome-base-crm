<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class VoipController extends Controller
{
    /**
     * Get VoIP provider configuration status.
     */
    public function status(): JsonResponse
    {
        $config = DB::table('integrations')
            ->where('provider', 'voip')
            ->first();

        if (! $config) {
            return response()->json(['data' => ['connected' => false]]);
        }

        $settings = json_decode($config->settings, true) ?? [];

        return response()->json([
            'data' => [
                'connected'    => (bool) $config->active,
                'voip_provider' => $settings['voip_provider'] ?? null,
                'connected_at' => $config->created_at,
            ],
        ]);
    }

    /**
     * Configure VoIP provider.
     */
    public function configure(Request $request): JsonResponse
    {
        $request->validate([
            'voip_provider'  => 'required|string|in:twilio,vonage,plivo',
            'account_sid'    => 'required|string',
            'auth_token'     => 'required|string',
            'phone_number'   => 'required|string',
        ]);

        $provider = $request->input('voip_provider');
        $valid = $this->validateCredentials(
            $provider,
            $request->input('account_sid'),
            $request->input('auth_token')
        );

        DB::table('integrations')->updateOrInsert(
            ['provider' => 'voip'],
            [
                'active'   => true,
                'settings' => json_encode([
                    'voip_provider' => $provider,
                    'account_sid'   => $request->input('account_sid'),
                    'auth_token'    => $request->input('auth_token'),
                    'phone_number'  => $request->input('phone_number'),
                    'recording'     => $request->boolean('recording', false),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'data'    => ['connected' => true, 'voip_provider' => $provider],
            'message' => 'VoIP provider configured.',
        ]);
    }

    /**
     * Disconnect VoIP provider.
     */
    public function disconnect(): JsonResponse
    {
        DB::table('integrations')->where('provider', 'voip')->delete();

        return response()->json(['message' => 'VoIP provider disconnected.']);
    }

    /**
     * Initiate a call (click-to-call).
     */
    public function call(Request $request): JsonResponse
    {
        $request->validate([
            'contact_id' => 'required|integer',
            'phone'      => 'required|string',
        ]);

        [$settings, $error] = $this->getActiveConfig();

        if ($error) {
            return $error;
        }

        $contact = DB::table('persons')->where('id', $request->input('contact_id'))->first();

        if (! $contact) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $provider = $settings['voip_provider'] ?? 'twilio';
        $callResult = $this->initiateCall($provider, $settings, $request->input('phone'));

        if (! $callResult['success']) {
            return response()->json(['message' => $callResult['error'] ?? 'Failed to initiate call'], 502);
        }

        // Auto-log as call activity
        $activityId = DB::table('activities')->insertGetId([
            'title'         => 'Call to ' . ($contact->name ?? 'Unknown'),
            'type'          => 'call',
            'comment'       => 'Outbound call initiated via ' . $provider,
            'schedule_from' => now(),
            'schedule_to'   => now(),
            'is_done'       => false,
            'user_id'       => auth()->id() ?? 1,
            'additional'    => json_encode([
                'call_sid'      => $callResult['call_sid'] ?? null,
                'phone'         => $request->input('phone'),
                'voip_provider' => $provider,
                'status'        => 'initiated',
                'recording'     => $settings['recording'] ?? false,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link activity to contact
        DB::table('person_activities')->insert([
            'activity_id' => $activityId,
            'person_id'   => $contact->id,
        ]);

        // Store in call_logs
        DB::table('call_logs')->insert([
            'activity_id'   => $activityId,
            'contact_id'    => $contact->id,
            'phone_number'  => $request->input('phone'),
            'direction'     => 'outbound',
            'voip_provider' => $provider,
            'call_sid'      => $callResult['call_sid'] ?? null,
            'status'        => 'initiated',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'data' => [
                'activity_id' => $activityId,
                'call_sid'    => $callResult['call_sid'] ?? null,
                'status'      => 'initiated',
            ],
            'message' => 'Call initiated.',
        ], 201);
    }

    /**
     * Update call status (webhook endpoint for provider callbacks).
     */
    public function webhook(Request $request): JsonResponse
    {
        $callSid = $request->input('CallSid') ?? $request->input('call_sid');
        $status = $request->input('CallStatus') ?? $request->input('status');
        $duration = $request->input('CallDuration') ?? $request->input('duration');
        $recordingUrl = $request->input('RecordingUrl') ?? $request->input('recording_url');

        if (! $callSid) {
            return response()->json(['message' => 'call_sid required'], 422);
        }

        $log = DB::table('call_logs')->where('call_sid', $callSid)->first();

        if (! $log) {
            return response()->json(['message' => 'Call not found'], 404);
        }

        $updates = [
            'status'     => $status ?? $log->status,
            'updated_at' => now(),
        ];

        if ($duration) {
            $updates['duration_seconds'] = (int) $duration;
        }

        if ($recordingUrl) {
            $updates['recording_url'] = $recordingUrl;
        }

        DB::table('call_logs')->where('call_sid', $callSid)->update($updates);

        // Update the activity too
        if ($log->activity_id) {
            $activity = DB::table('activities')->where('id', $log->activity_id)->first();

            if ($activity) {
                $additional = json_decode($activity->additional, true) ?? [];
                $additional['status'] = $status ?? $additional['status'] ?? 'unknown';

                if ($duration) {
                    $additional['duration_seconds'] = (int) $duration;
                }
                if ($recordingUrl) {
                    $additional['recording_url'] = $recordingUrl;
                }

                $activityUpdates = [
                    'additional'  => json_encode($additional),
                    'updated_at'  => now(),
                ];

                if (in_array($status, ['completed', 'failed', 'busy', 'no-answer', 'canceled'])) {
                    $activityUpdates['is_done'] = true;
                    $activityUpdates['schedule_to'] = now();
                }

                DB::table('activities')->where('id', $log->activity_id)->update($activityUpdates);
            }
        }

        return response()->json(['message' => 'Call status updated.']);
    }

    /**
     * Get call log for a contact.
     */
    public function contactCalls(int $contactId): JsonResponse
    {
        $logs = DB::table('call_logs')
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($log) {
                return [
                    'id'               => $log->id,
                    'activity_id'      => $log->activity_id,
                    'phone_number'     => $log->phone_number,
                    'direction'        => $log->direction,
                    'status'           => $log->status,
                    'duration_seconds' => $log->duration_seconds,
                    'recording_url'    => $log->recording_url,
                    'voip_provider'    => $log->voip_provider,
                    'created_at'       => $log->created_at,
                ];
            });

        return response()->json(['data' => $logs]);
    }

    /**
     * Get call recording URL.
     */
    public function recording(int $callLogId): JsonResponse
    {
        $log = DB::table('call_logs')->where('id', $callLogId)->first();

        if (! $log) {
            return response()->json(['message' => 'Call log not found'], 404);
        }

        if (! $log->recording_url) {
            return response()->json(['message' => 'No recording available'], 404);
        }

        return response()->json([
            'data' => [
                'recording_url'    => $log->recording_url,
                'duration_seconds' => $log->duration_seconds,
            ],
        ]);
    }

    /**
     * Validate provider credentials (best-effort ping).
     */
    private function validateCredentials(string $provider, string $sid, string $token): bool
    {
        // In production, this would ping the provider's API to validate.
        // For now, we just accept non-empty credentials.
        return ! empty($sid) && ! empty($token);
    }

    /**
     * Initiate call via the configured provider.
     */
    private function initiateCall(string $provider, array $settings, string $toPhone): array
    {
        $fromPhone = $settings['phone_number'] ?? '';
        $sid = $settings['account_sid'] ?? '';
        $token = $settings['auth_token'] ?? '';

        switch ($provider) {
            case 'twilio':
                return $this->initiateTwilioCall($sid, $token, $fromPhone, $toPhone, $settings['recording'] ?? false);
            case 'vonage':
                return $this->initiateVonageCall($sid, $token, $fromPhone, $toPhone);
            case 'plivo':
                return $this->initiatePlivoCall($sid, $token, $fromPhone, $toPhone);
            default:
                return ['success' => false, 'error' => 'Unsupported provider'];
        }
    }

    /**
     * Initiate via Twilio.
     */
    private function initiateTwilioCall(string $sid, string $token, string $from, string $to, bool $record): array
    {
        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls.json", [
                'To'     => $to,
                'From'   => $from,
                'Url'    => 'http://demo.twilio.com/docs/voice.xml',
                'Record' => $record ? 'true' : 'false',
            ]);

        if (! $response->ok()) {
            return ['success' => false, 'error' => 'Twilio API error: ' . $response->status()];
        }

        return [
            'success'  => true,
            'call_sid' => $response->json('sid'),
        ];
    }

    /**
     * Initiate via Vonage.
     */
    private function initiateVonageCall(string $apiKey, string $apiSecret, string $from, string $to): array
    {
        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->post('https://api.nexmo.com/v1/calls', [
                'to'      => [['type' => 'phone', 'number' => $to]],
                'from'    => ['type' => 'phone', 'number' => $from],
                'ncco'    => [['action' => 'talk', 'text' => 'Connecting your call.']],
            ]);

        if (! $response->ok()) {
            return ['success' => false, 'error' => 'Vonage API error: ' . $response->status()];
        }

        return [
            'success'  => true,
            'call_sid' => $response->json('uuid'),
        ];
    }

    /**
     * Initiate via Plivo.
     */
    private function initiatePlivoCall(string $authId, string $authToken, string $from, string $to): array
    {
        $response = Http::withBasicAuth($authId, $authToken)
            ->post("https://api.plivo.com/v1/Account/{$authId}/Call/", [
                'to'      => $to,
                'from'    => $from,
                'answer_url' => 'https://s3.amazonaws.com/plivosupport/Answer.xml',
            ]);

        if (! $response->ok()) {
            return ['success' => false, 'error' => 'Plivo API error: ' . $response->status()];
        }

        return [
            'success'  => true,
            'call_sid' => $response->json('request_uuid'),
        ];
    }

    /**
     * Get active config.
     */
    private function getActiveConfig(): array
    {
        $config = DB::table('integrations')
            ->where('provider', 'voip')
            ->where('active', true)
            ->first();

        if (! $config) {
            return [null, response()->json(['message' => 'VoIP provider not connected'], 422)];
        }

        return [json_decode($config->settings, true) ?? [], null];
    }
}
