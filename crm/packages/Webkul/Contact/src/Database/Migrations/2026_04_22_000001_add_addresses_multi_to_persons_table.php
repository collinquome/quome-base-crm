<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Swap the single-address EAV field for a JSON `addresses` column that
     * holds an array of typed addresses (home/work/mailing/other) with US
     * as the default country. Back-fills any existing `address` value into
     * addresses[0] with type=home, and removes the single-address EAV
     * attribute row so it stops rendering on forms.
     *
     * Why: household insurance customers routinely have separate billing
     * and property addresses; the stock Krayin address type only handles
     * one. Keeping `persons.address` in place keeps old data readable if
     * anything still references it.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('persons', 'addresses')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->json('addresses')->nullable()->after('address');
            });
        }

        DB::table('persons')
            ->whereNotNull('address')
            ->whereNull('addresses')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $decoded = is_string($row->address) ? json_decode($row->address, true) : null;

                    if (! is_array($decoded) || empty(array_filter($decoded))) {
                        continue;
                    }

                    $migrated = [[
                        'address_type'    => 'home',
                        'address_line_1'  => $decoded['address'] ?? null,
                        'address_line_2'  => null,
                        'city'            => $decoded['city'] ?? null,
                        'state'           => $decoded['state'] ?? null,
                        'postcode'        => $decoded['postcode'] ?? null,
                        'country'         => $decoded['country'] ?? 'US',
                    ]];

                    DB::table('persons')
                        ->where('id', $row->id)
                        ->update(['addresses' => json_encode($migrated)]);
                }
            });

        DB::table('attributes')
            ->where('entity_type', 'persons')
            ->where('code', 'address')
            ->delete();
    }

    public function down(): void
    {
        // Leave the column in place on rollback — safer than dropping user data.
        // The single-address EAV attribute is not re-created here because the
        // repeater fully replaces it.
    }
};
