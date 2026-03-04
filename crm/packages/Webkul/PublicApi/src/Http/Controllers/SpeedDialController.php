<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class SpeedDialController extends Controller
{
    /**
     * Get speed dial contacts (favorites + recently contacted).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $limit = min($request->input('limit', 20), 50);

        // Get favorited contacts
        $favorites = DB::table('speed_dial_favorites')
            ->join('persons', 'speed_dial_favorites.person_id', '=', 'persons.id')
            ->where('speed_dial_favorites.user_id', $userId)
            ->orderBy('speed_dial_favorites.sort_order')
            ->select(
                'persons.id',
                'persons.name',
                'persons.emails',
                'persons.contact_numbers',
                'persons.organization_id',
                DB::raw("'favorite' as source"),
                'speed_dial_favorites.sort_order',
            )
            ->limit($limit)
            ->get()
            ->map(fn ($p) => $this->formatContact($p));

        // Get recently contacted (from activities)
        $recentlyContacted = DB::table('activity_participants')
            ->join('activities', 'activity_participants.activity_id', '=', 'activities.id')
            ->join('persons', 'activity_participants.person_id', '=', 'persons.id')
            ->where('activities.user_id', $userId)
            ->whereIn('activities.type', ['call', 'meeting'])
            ->whereNotIn('persons.id', $favorites->pluck('id')->toArray())
            ->orderByDesc('activities.created_at')
            ->select(
                'persons.id',
                'persons.name',
                'persons.emails',
                'persons.contact_numbers',
                'persons.organization_id',
                DB::raw("'recent' as source"),
                DB::raw('NULL as sort_order'),
            )
            ->distinct()
            ->limit($limit)
            ->get()
            ->map(fn ($p) => $this->formatContact($p));

        return response()->json([
            'data' => [
                'favorites' => $favorites->values(),
                'recent'    => $recentlyContacted->values(),
            ],
        ]);
    }

    /**
     * Add a contact to speed dial favorites.
     */
    public function addFavorite(Request $request): JsonResponse
    {
        $request->validate([
            'person_id' => 'required|integer|exists:persons,id',
        ]);

        $userId = auth()->id();
        $personId = $request->input('person_id');

        // Check if already a favorite
        $exists = DB::table('speed_dial_favorites')
            ->where('user_id', $userId)
            ->where('person_id', $personId)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Contact is already a favorite'], 422);
        }

        // Get next sort order
        $maxSort = DB::table('speed_dial_favorites')
            ->where('user_id', $userId)
            ->max('sort_order') ?? 0;

        DB::table('speed_dial_favorites')->insert([
            'user_id'    => $userId,
            'person_id'  => $personId,
            'sort_order' => $maxSort + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $person = DB::table('persons')->where('id', $personId)->first();

        return response()->json([
            'data'    => $this->formatContact($person, 'favorite', $maxSort + 1),
            'message' => 'Contact added to speed dial.',
        ], 201);
    }

    /**
     * Remove a contact from speed dial favorites.
     */
    public function removeFavorite(int $personId): JsonResponse
    {
        $deleted = DB::table('speed_dial_favorites')
            ->where('user_id', auth()->id())
            ->where('person_id', $personId)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Favorite not found'], 404);
        }

        return response()->json(['message' => 'Contact removed from speed dial.']);
    }

    /**
     * Reorder speed dial favorites.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:persons,id',
        ]);

        $userId = auth()->id();
        $order = $request->input('order');

        foreach ($order as $index => $personId) {
            DB::table('speed_dial_favorites')
                ->where('user_id', $userId)
                ->where('person_id', $personId)
                ->update([
                    'sort_order'  => $index + 1,
                    'updated_at'  => now(),
                ]);
        }

        return response()->json(['message' => 'Speed dial reordered.']);
    }

    /**
     * Initiate a quick call via VoIP for a speed dial contact.
     */
    public function quickCall(int $personId): JsonResponse
    {
        $person = DB::table('persons')->where('id', $personId)->first();

        if (! $person) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $numbers = json_decode($person->contact_numbers, true) ?? [];
        $primaryNumber = null;

        foreach ($numbers as $num) {
            if (is_array($num) && ! empty($num['value'])) {
                $primaryNumber = $num['value'];
                break;
            }
            if (is_string($num) && ! empty($num)) {
                $primaryNumber = $num;
                break;
            }
        }

        if (! $primaryNumber) {
            return response()->json(['message' => 'Contact has no phone number'], 422);
        }

        // Check if VoIP is configured
        $voipConfig = DB::table('integrations')
            ->where('provider', 'voip')
            ->where('active', true)
            ->first();

        return response()->json([
            'data' => [
                'person_id'    => $person->id,
                'person_name'  => $person->name,
                'phone_number' => $primaryNumber,
                'voip_ready'   => (bool) $voipConfig,
            ],
        ]);
    }

    /**
     * Format a contact for speed dial response.
     */
    private function formatContact(object $person, ?string $source = null, ?int $sortOrder = null): array
    {
        $emails = json_decode($person->emails ?? '[]', true) ?? [];
        $numbers = json_decode($person->contact_numbers ?? '[]', true) ?? [];

        $primaryEmail = null;
        foreach ($emails as $e) {
            if (is_array($e) && ! empty($e['value'])) {
                $primaryEmail = $e['value'];
                break;
            }
        }

        $primaryPhone = null;
        foreach ($numbers as $n) {
            if (is_array($n) && ! empty($n['value'])) {
                $primaryPhone = $n['value'];
                break;
            }
            if (is_string($n) && ! empty($n)) {
                $primaryPhone = $n;
                break;
            }
        }

        return [
            'id'              => $person->id,
            'name'            => $person->name,
            'email'           => $primaryEmail,
            'phone'           => $primaryPhone,
            'organization_id' => $person->organization_id ?? null,
            'source'          => $source ?? ($person->source ?? 'unknown'),
            'sort_order'      => $sortOrder ?? ($person->sort_order ?? null),
        ];
    }
}
