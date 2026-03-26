<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        // Update existing types
        DB::table('lead_types')->where('id', 1)->update(['name' => 'Lead']);
        DB::table('lead_types')->where('id', 2)->update(['name' => 'Prospect']);

        // Add new types if they don't exist
        if (! DB::table('lead_types')->where('name', 'Client')->exists()) {
            DB::table('lead_types')->insert([
                'name'       => 'Client',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('lead_types')->where('name', 'Inactive')->exists()) {
            DB::table('lead_types')->insert([
                'name'       => 'Inactive',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Revert to original types
        DB::table('lead_types')->where('id', 1)->update(['name' => 'New Business']);
        DB::table('lead_types')->where('id', 2)->update(['name' => 'Existing Business']);

        // Remove added types (reassign leads first)
        $leadTypeId = DB::table('lead_types')->where('name', 'Lead')->value('id') ?? 1;

        $removeIds = DB::table('lead_types')
            ->whereIn('name', ['Client', 'Inactive'])
            ->pluck('id');

        if ($removeIds->isNotEmpty()) {
            DB::table('leads')
                ->whereIn('lead_type_id', $removeIds)
                ->update(['lead_type_id' => $leadTypeId]);

            DB::table('lead_types')->whereIn('id', $removeIds)->delete();
        }
    }
};
