<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an optional address JSON column to persons plus the matching EAV
     * attribute row so it surfaces on the contact/person and lead-create
     * forms. Matches the pattern organizations already use.
     *
     * Single-address for now (home vs work labelled addresses is a larger
     * follow-up — stock Krayin's type=address component renders one address).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('persons', 'address')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->json('address')->nullable()->after('contact_numbers');
            });
        }

        $exists = DB::table('attributes')
            ->where('entity_type', 'persons')
            ->where('code', 'address')
            ->exists();

        if (! $exists) {
            $now = Carbon::now();

            $maxSortOrder = DB::table('attributes')
                ->where('entity_type', 'persons')
                ->max('sort_order') ?: 0;

            DB::table('attributes')->insert([
                'code'            => 'address',
                'name'            => 'Address',
                'entity_type'     => 'persons',
                'type'            => 'address',
                'is_required'     => 0,
                'is_unique'       => 0,
                'is_user_defined' => 0,
                'quick_add'       => 1,
                'sort_order'      => $maxSortOrder + 1,
                'validation'      => null,
                'lookup_type'     => null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'persons')
            ->where('code', 'address')
            ->delete();

        // Leave the column in place on rollback — safer than dropping user data.
    }
};
