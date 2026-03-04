<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OutlookCalendarController extends Controller
{
    /**
     * Get Outlook Calendar connection status.
     */
    public function status(): JsonResponse
    {
        $config = DB::table('integrations')
            ->where('provider', 'outlook_calendar')
            ->first();

        if (! $config) {
            return response()->json(['data' => ['connected' => false]]);
        }

        $settings = json_decode($config->settings, true) ?? [];

        return response()->json([
            'data' => [
                'connected'    => (bool) $config->active,
                'email'        => $settings['email'] ?? null,
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
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'redirect_uri'  => 'required|url',
            'tenant_id'     => 'sometimes|string',
        ]);

        $tenantId = $request->input('tenant_id', 'common');

        DB::table('integrations')->updateOrInsert(
            ['provider' => 'outlook_calendar'],
            [
                'active'   => false,
                'settings' => json_encode([
                    'client_id'     => $request->input('client_id'),
                    'client_secret' => $request->input('client_secret'),
                    'redirect_uri'  => $request->input('redirect_uri'),
                    'tenant_id'     => $tenantId,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $params = http_build_query([
            'client_id'     => $request->input('client_id'),
            'response_type' => 'code',
            'redirect_uri'  => $request->input('redirect_uri'),
            'scope'         => 'openid profile email Calendars.ReadWrite',
            'response_mode' => 'query',
        ]);

        return response()->json([
            'data' => [
                'auth_url' => "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?{$params}",
            ],
        ]);
    }

    /**
     * Handle OAuth2 callback.
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $config = DB::table('integrations')->where('provider', 'outlook_calendar')->first();

        if (! $config) {
            return response()->json(['message' => 'Outlook Calendar not initialized'], 422);
        }

        $settings = json_decode($config->settings, true) ?? [];
        $tenantId = $settings['tenant_id'] ?? 'common';

        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'client_id'     => $settings['client_id'] ?? '',
            'client_secret' => $settings['client_secret'] ?? '',
            'code'          => $request->input('code'),
            'redirect_uri'  => $settings['redirect_uri'] ?? '',
            'grant_type'    => 'authorization_code',
            'scope'         => 'openid profile email Calendars.ReadWrite',
        ]);

        if (! $response->ok()) {
            return response()->json(['message' => 'Token exchange failed'], 502);
        }

        $tokens = $response->json();

        DB::table('integrations')->where('provider', 'outlook_calendar')->update([
            'active'   => true,
            'settings' => json_encode(array_merge($settings, [
                'access_token'  => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at'    => now()->addSeconds($tokens['expires_in'] ?? 3600)->toIso8601String(),
            ])),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data'    => ['connected' => true],
            'message' => 'Outlook Calendar connected.',
        ]);
    }

    /**
     * Disconnect.
     */
    public function disconnect(): JsonResponse
    {
        DB::table('integrations')->where('provider', 'outlook_calendar')->delete();

        return response()->json(['message' => 'Outlook Calendar disconnected.']);
    }

    /**
     * List upcoming events.
     */
    public function events(Request $request): JsonResponse
    {
        [$settings, $error] = $this->getActiveConfig();

        if ($error) {
            return $error;
        }

        $response = Http::withToken($settings['access_token'] ?? '')
            ->get('https://graph.microsoft.com/v1.0/me/calendarview', [
                'startdatetime' => now()->toIso8601String(),
                'enddatetime'   => now()->addDays(30)->toIso8601String(),
                '$top'          => $request->input('limit', 25),
                '$orderby'      => 'start/dateTime',
            ]);

        if (! $response->ok()) {
            return response()->json(['message' => 'Failed to fetch events'], 502);
        }

        $items = $response->json('value') ?? [];
        $events = array_map(function ($item) {
            return [
                'id'          => $item['id'],
                'subject'     => $item['subject'] ?? '',
                'body'        => $item['bodyPreview'] ?? '',
                'start'       => $item['start']['dateTime'] ?? null,
                'end'         => $item['end']['dateTime'] ?? null,
                'location'    => $item['location']['displayName'] ?? null,
                'attendees'   => array_map(fn ($a) => $a['emailAddress']['address'] ?? '', $item['attendees'] ?? []),
                'link'        => $item['webLink'] ?? null,
            ];
        }, $items);

        return response()->json(['data' => $events]);
    }

    /**
     * Sync a CRM activity to Outlook Calendar.
     */
    public function syncActivity(Request $request): JsonResponse
    {
        $request->validate([
            'activity_id' => 'required|integer',
        ]);

        [$settings, $error] = $this->getActiveConfig();

        if ($error) {
            return $error;
        }

        $activity = DB::table('activities')->where('id', $request->input('activity_id'))->first();

        if (! $activity) {
            return response()->json(['message' => 'Activity not found'], 404);
        }

        $eventData = [
            'subject' => $activity->title,
            'body'    => ['contentType' => 'text', 'content' => $activity->comment ?? ''],
            'start'   => [
                'dateTime' => $activity->schedule_from ?? now()->toIso8601String(),
                'timeZone' => config('app.timezone', 'UTC'),
            ],
            'end' => [
                'dateTime' => $activity->schedule_to ?? now()->addHour()->toIso8601String(),
                'timeZone' => config('app.timezone', 'UTC'),
            ],
        ];

        $response = Http::withToken($settings['access_token'] ?? '')
            ->post('https://graph.microsoft.com/v1.0/me/events', $eventData);

        if (! $response->ok()) {
            return response()->json(['message' => 'Failed to create calendar event'], 502);
        }

        $event = $response->json();

        DB::table('calendar_syncs')->insert([
            'activity_id'     => $activity->id,
            'provider'        => 'outlook',
            'external_id'     => $event['id'] ?? null,
            'external_link'   => $event['webLink'] ?? null,
            'sync_direction'  => 'outbound',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json([
            'data' => [
                'activity_id' => $activity->id,
                'event_id'    => $event['id'] ?? null,
                'link'        => $event['webLink'] ?? null,
            ],
            'message' => 'Activity synced to Outlook Calendar.',
        ], 201);
    }

    /**
     * Get active config.
     */
    private function getActiveConfig(): array
    {
        $config = DB::table('integrations')
            ->where('provider', 'outlook_calendar')
            ->where('active', true)
            ->first();

        if (! $config) {
            return [null, response()->json(['message' => 'Outlook Calendar not connected'], 422)];
        }

        return [json_decode($config->settings, true) ?? [], null];
    }
}
