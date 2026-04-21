<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Canonical list of insurance policy titles seeded as products.
     *
     * Titles are both the SKU and display name. Case-only duplicates from the
     * source list (PACKAGE/Package, CYCLE/Cycle) are collapsed in favor of the
     * uppercase variant, since MySQL's default utf8mb4_unicode_ci treats the
     * sku unique index as case-insensitive.
     */
    private array $titles = [
        'AUTOP', 'HOME', 'AUTOB', 'CPKGE', 'BOP', 'WC', 'DFIRE', 'CGL',
        'CUMB', 'PPKGE', 'PROP', 'LIFE', 'PHY DAMAGE', 'PUMB', 'FLOOD',
        'CYCLE', 'PROF', 'BOPGL', 'FARM', 'X EXCESS', 'BOND', 'E&O',
        'BOAT', 'GARAGE', 'CPKG', 'D&O', 'RECV', 'MHOME', 'POLLUTION',
        'BOPPR', 'NTL', 'INMRC', 'BLDRK', 'AUTOB-PKGE', 'BOP/UMB',
        'CONDO', 'Pkg-INMRC', 'EPLI', 'CYBER', 'CPROP', 'TENANT',
        'Pkg-PHYS D', 'MTRTK', 'PUMBR', 'CARGO', 'YACHT', 'VACANT',
        'LIQUOR', 'AUTOC', 'Personal', 'Pkg-CLIA', 'CGL/UMB',
        'PHY-DAMAGE', 'PACKAGE', 'CGL/PROF', 'Mgmt Pkg', 'Pkg-CGL',
        'INMRP', 'RV', 'X-CARGO', 'OCC ACC', 'OTHER', 'Pkg-PROP',
        'AUTOB/CGL', 'TRK/GL', 'ACCIDENT', 'PHYS DAM', 'CRIME',
        'NON-TRUCK', 'HEALTH', 'PLMSC', 'TRKRS', 'XUMB', 'Pkg-CUMBR',
        'DISABILITY', 'Pkg-TRKRS', 'WORK', 'X-CYBER', 'Pkg-HOME',
        'XCYBER', 'TRANS', 'CUMBR', 'AUTO', 'Mgmt Liab', 'CAUTO',
        'Pkg-MTRTK', 'GL/Poll', 'CONT CARGO', 'RR', 'EXPORTERS',
        'TERM LIFE', 'MISC', 'ROAD MAST', 'CRIM', 'MEMBR', 'Pkg-CONTR',
        'SURE', 'NON TRUCK', 'PET', 'Pkg-DFIRE', 'EO', 'SNOWMOBILE',
        'SCHPR', 'LIAB', 'TRAVEL', 'VA', 'ROADSIDE', 'HNOA', 'VAP',
        'MOPRO',
    ];

    public function up(): void
    {
        $now = Carbon::now();

        $rows = array_map(fn ($title) => [
            'sku'        => $title,
            'name'       => $title,
            'quantity'   => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->titles);

        DB::table('products')->upsert(
            $rows,
            ['sku'],
            ['name', 'updated_at'],
        );
    }

    public function down(): void
    {
        DB::table('products')->whereIn('sku', $this->titles)->delete();
    }
};
