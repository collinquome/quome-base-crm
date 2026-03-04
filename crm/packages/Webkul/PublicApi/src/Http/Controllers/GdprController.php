<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class GdprController extends Controller
{
    /**
     * Export all data for a contact as JSON.
     */
    public function export(int $contactId): JsonResponse
    {
        $person = DB::table('persons')->where('id', $contactId)->whereNull('deleted_at')->first();

        if (! $person) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        // Gather all related data
        $leads = DB::table('leads')->where('person_id', $contactId)->whereNull('deleted_at')->get();
        $activities = DB::table('activities')
            ->join('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
            ->where('activity_participants.person_id', $contactId)
            ->select('activities.*')
            ->get();
        $emails = DB::table('emails')->where('person_id', $contactId)->get();
        $tags = DB::table('person_tags')
            ->join('tags', 'person_tags.tag_id', '=', 'tags.id')
            ->where('person_tags.person_id', $contactId)
            ->select('tags.*')
            ->get();

        return response()->json([
            'data' => [
                'contact'    => $person,
                'leads'      => $leads,
                'activities' => $activities,
                'emails'     => $emails,
                'tags'       => $tags,
                'exported_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Anonymize/delete all data for a contact (right to deletion).
     */
    public function erase(Request $request, int $contactId): JsonResponse
    {
        $request->validate([
            'confirm' => 'required|boolean|accepted',
        ]);

        $person = DB::table('persons')->where('id', $contactId)->whereNull('deleted_at')->first();

        if (! $person) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        $deleted = [
            'emails'     => 0,
            'activities' => 0,
            'tags'       => 0,
            'leads'      => 0,
        ];

        // Delete related emails
        $deleted['emails'] = DB::table('emails')->where('person_id', $contactId)->delete();

        // Delete activity participations and orphaned activities
        $activityIds = DB::table('activity_participants')
            ->where('person_id', $contactId)
            ->pluck('activity_id');
        DB::table('activity_participants')->where('person_id', $contactId)->delete();
        if ($activityIds->isNotEmpty()) {
            // Delete activities that no longer have any participants
            $orphaned = DB::table('activities')
                ->whereIn('id', $activityIds)
                ->whereNotIn('id', function ($q) {
                    $q->select('activity_id')->from('activity_participants');
                })
                ->pluck('id');
            $deleted['activities'] = DB::table('activities')->whereIn('id', $orphaned)->delete();
        }

        // Delete tag associations
        $deleted['tags'] = DB::table('person_tags')->where('person_id', $contactId)->delete();

        // Soft-delete leads associated with this contact
        $deleted['leads'] = DB::table('leads')
            ->where('person_id', $contactId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        // Anonymize the person record
        DB::table('persons')->where('id', $contactId)->update([
            'name'       => '[Deleted]',
            'emails'     => json_encode([]),
            'contact_numbers' => json_encode([]),
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'contact_id' => $contactId,
                'anonymized' => true,
                'deleted'    => $deleted,
            ],
            'message' => 'Contact data has been anonymized and related records deleted.',
        ]);
    }

    /**
     * Get consent status for a contact.
     */
    public function consentStatus(int $contactId): JsonResponse
    {
        $person = DB::table('persons')->where('id', $contactId)->first();

        if (! $person) {
            return response()->json(['message' => 'Contact not found'], 404);
        }

        // Check for consent-related custom attributes
        $consentAttrs = DB::table('attribute_values')
            ->join('attributes', 'attribute_values.attribute_id', '=', 'attributes.id')
            ->where('attribute_values.entity_type', 'persons')
            ->where('attribute_values.entity_id', $contactId)
            ->where('attributes.code', 'like', 'consent_%')
            ->select('attributes.code', 'attributes.name', 'attribute_values.text_value', 'attribute_values.boolean_value')
            ->get();

        return response()->json([
            'data' => [
                'contact_id'       => $contactId,
                'consent_records'  => $consentAttrs,
                'has_consent_data' => $consentAttrs->isNotEmpty(),
            ],
        ]);
    }
}
