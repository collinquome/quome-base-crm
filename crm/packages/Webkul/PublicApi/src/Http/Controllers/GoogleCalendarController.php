<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GoogleCalendarController extends Controller
{
    /**
     * Get Google Calendar connection status.
     */
    public function status(): JsonResponse
    {
        $config = DB::table('integrations')
            ->where('provider', 'google_calendar')
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
        ]);

        DB::table('integrations')->updateOrInsert(
            ['provider' => 'google_calendar'],
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
            'redirect_uri'  => $request->input('redirect_uri'),
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);

        return response()->json([
            'data' => [
                'auth_url' => "https://accounts.google.com/o/oauth2/v2/auth?{$params}",
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

        $config = DB::table('integrations')->where('provider', 'google_calendar')->first();

        if (! $config) {
            return response()->json(['message' => 'Google Calendar not initialized'], 422);
        }

        $settings = json_decode($config->settings, true) ?? [];

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code'          => $request->input('code'),
            'client_id'     => $settings['client_id'] ?? '',
            'client_secret' => $settings['client_secret'] ?? '',
            'redirect_uri'  => $settings['redirect_uri'] ?? '',
            'grant_type'    => 'authorization_code',
        ]);

        if (! $response->ok()) {
            return response()->json(['message' => 'Token exchange failed'], 502);
        }

        $tokens = $response->json();

        DB::table('integrations')->where('provider', 'google_calendar')->update([
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
            'message' => 'Google Calendar connected.',
        ]);
    }

    /**
     * Disconnect.
     */
    public function disconnect(): JsonResponse
    {
        DB::table('integrations')->where('provider', 'google_calendar')->delete();

        return response()->json(['message' => 'Google Calendar disconnected.']);
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
            ->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                'maxResults'  => $request->input('limit', 25),
                'timeMin'     => now()->toIso8601String(),
                'orderBy'     => 'startTime',
                'singleEvents' => true,
            ]);

        if (! $response->ok()) {
            return response()->json(['message' => 'Failed to fetch events'], 502);
        }

        $items = $response->json('items') ?? [];
        $events = array_map(function ($item) {
            return [
                'id'          => $item['id'],
                'summary'     => $item['summary'] ?? '',
                'description' => $item['description'] ?? '',
                'start'       => $item['start']['dateTime'] ?? $item['start']['date'] ?? null,
                'end'         => $item['end']['dateTime'] ?? $item['end']['date'] ?? null,
                'location'    => $item['location'] ?? null,
                'attendees'   => array_map(fn ($a) => $a['email'] ?? '', $item['attendees'] ?? []),
                'link'        => $item['htmlLink'] ?? null,
            ];
        }, $items);

        return response()->json(['data' => $events]);
    }

    /**
     * Sync a CRM activity to Google Calendar.
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
            'summary'     => $activity->title,
            'description' => $activity->comment ?? '',
            'start'       => ['dateTime' => $activity->schedule_from ?? now()->toIso8601String()],
            'end'         => ['dateTime' => $activity->schedule_to ?? now()->addHour()->toIso8601String()],
        ];

        $response = Http::withToken($settings['access_token'] ?? '')
            ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events', $eventData);

        if (! $response->ok()) {
            return response()->json(['message' => 'Failed to create calendar event'], 502);
        }

        $event = $response->json();

        DB::table('calendar_syncs')->insert([
            'activity_id'     => $activity->id,
            'provider'        => 'google',
            'external_id'     => $event['id'] ?? null,
            'external_link'   => $event['htmlLink'] ?? null,
            'sync_direction'  => 'outbound',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json([
            'data' => [
                'activity_id' => $activity->id,
                'event_id'    => $event['id'] ?? null,
                'link'        => $event['htmlLink'] ?? null,
            ],
            'message' => 'Activity synced to Google Calendar.',
        ], 201);
    }

    /**
     * Get active config.
     */
    private function getActiveConfig(): array
    {
        $config = DB::table('integrations')
            ->where('provider', 'google_calendar')
            ->where('active', true)
            ->first();

        if (! $config) {
            return [null, response()->json(['message' => 'Google Calendar not connected'], 422)];
        }

        return [json_decode($config->settings, true) ?? [], null];
    }
}
