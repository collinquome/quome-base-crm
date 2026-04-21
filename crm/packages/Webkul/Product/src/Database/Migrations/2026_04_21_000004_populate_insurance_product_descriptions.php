<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Descriptions for the seeded insurance product catalog. Standard
     * industry abbreviations (CGL, BOP, HNOA, etc.) are sourced from the
     * Florida OIR Line-of-Business matrix and general agency conventions.
     * A few agency-specific codes are marked "(please verify)" so they're
     * obviously editable in the admin UI.
     */
    private array $descriptions = [
        // Personal auto / home
        'AUTOP'      => 'Personal Auto',
        'HOME'       => 'Homeowners',
        'DFIRE'      => 'Dwelling Fire (non-owner occupied or rental property)',
        'MHOME'      => 'Mobile Home',
        'CONDO'      => 'Condominium (HO-6)',
        'TENANT'     => 'Renters / Tenant',
        'Personal'   => 'Personal Lines (umbrella category)',
        'VACANT'     => 'Vacant Property',
        'ROADSIDE'   => 'Roadside Assistance',

        // Life & health
        'LIFE'       => 'Life Insurance',
        'TERM LIFE'  => 'Term Life Insurance',
        'HEALTH'     => 'Health Insurance',
        'DISABILITY' => 'Disability Insurance',
        'ACCIDENT'   => 'Accident Insurance',
        'TRAVEL'     => 'Travel Insurance',
        'PET'        => 'Pet Insurance',

        // Recreational / powersports
        'CYCLE'      => 'Motorcycle',
        'BOAT'       => 'Boat / Personal Watercraft',
        'YACHT'      => 'Yacht',
        'RV'         => 'Recreational Vehicle',
        'SNOWMOBILE' => 'Snowmobile',
        'MOPRO'      => 'Moped / Motor Protection (please verify)',

        // Flood & catastrophe
        'FLOOD'      => 'Flood Insurance (NFIP or private)',

        // Umbrellas / excess
        'PUMB'       => 'Personal Umbrella',
        'PUMBR'      => 'Personal Umbrella',
        'CUMB'       => 'Commercial Umbrella',
        'CUMBR'      => 'Commercial Umbrella',
        'XUMB'       => 'Excess Umbrella',
        'X EXCESS'   => 'Excess Policy',

        // Commercial auto & trucking
        'AUTO'       => 'Auto (generic)',
        'AUTOB'      => 'Business Auto',
        'AUTOC'      => 'Commercial Auto',
        'CAUTO'      => 'Commercial Auto',
        'AUTOB-PKGE' => 'Business Auto Package',
        'AUTOB/CGL'  => 'Business Auto with General Liability',
        'HNOA'       => 'Hired & Non-Owned Auto',
        'GARAGE'     => 'Garage / Auto Dealers',
        'TRKRS'      => 'Truckers Liability',
        'Pkg-TRKRS'  => 'Truckers Package',
        'MTRTK'      => 'Motor Truck Cargo',
        'Pkg-MTRTK'  => 'Motor Truck Package',
        'TRK/GL'     => 'Trucking with General Liability',
        'NON-TRUCK'  => 'Non-Trucking Liability',
        'NON TRUCK'  => 'Non-Trucking Liability',
        'CARGO'      => 'Cargo',
        'X-CARGO'    => 'Excess Cargo',
        'CONT CARGO' => 'Contingent Cargo',
        'TRANS'      => 'Transportation',
        'EXPORTERS'  => 'Exporters / Export Cargo',
        'OCC ACC'    => 'Occupational Accident (trucking owner-operators)',
        'PHY DAMAGE' => 'Auto Physical Damage',
        'PHY-DAMAGE' => 'Auto Physical Damage',
        'PHYS DAM'   => 'Auto Physical Damage',
        'Pkg-PHYS D' => 'Auto Physical Damage Package',
        'ROAD MAST'  => 'RoadMaster Trucking Coverage (please verify)',

        // Commercial property / package
        'PROP'       => 'Property',
        'CPROP'      => 'Commercial Property',
        'PACKAGE'    => 'Commercial Package Policy',
        'CPKGE'      => 'Commercial Package',
        'CPKG'       => 'Commercial Package',
        'PPKGE'      => 'Personal Package',
        'BLDRK'      => 'Builders Risk',
        'FARM'       => 'Farmowners / Farm Package',
        'INMRC'      => 'Commercial Inland Marine',
        'INMRP'      => 'Personal Inland Marine',
        'Pkg-INMRC'  => 'Inland Marine Package',
        'Pkg-HOME'   => 'Homeowners Package',
        'Pkg-DFIRE'  => 'Dwelling Fire Package',
        'Pkg-PROP'   => 'Property Package',

        // Commercial liability
        'BOP'        => 'Businessowners Policy',
        'BOPGL'      => 'BOP with General Liability',
        'BOPPR'      => 'BOP with Property',
        'BOP/UMB'    => 'BOP with Umbrella',
        'CGL'        => 'Commercial General Liability',
        'CGL/UMB'    => 'Commercial GL with Umbrella',
        'CGL/PROF'   => 'Commercial GL with Professional Liability',
        'Pkg-CGL'    => 'General Liability Package',
        'Pkg-CUMBR'  => 'Commercial Umbrella Package',
        'Pkg-CONTR'  => 'Contractors Package',
        'Pkg-CLIA'   => 'Contractors Liability Package (please verify)',
        'PROF'       => 'Professional Liability',
        'LIAB'       => 'Liability (general)',

        // Specialty liability
        'E&O'        => 'Errors & Omissions',
        'EO'         => 'Errors & Omissions',
        'D&O'        => 'Directors & Officers Liability',
        'EPLI'       => 'Employment Practices Liability',
        'CYBER'      => 'Cyber Liability',
        'X-CYBER'    => 'Excess Cyber Liability',
        'XCYBER'     => 'Excess Cyber Liability',
        'LIQUOR'     => 'Liquor Liability',
        'POLLUTION'  => 'Pollution Liability (Environmental Impairment)',
        'GL/Poll'    => 'General Liability with Pollution',
        'Mgmt Liab'  => 'Management Liability',
        'Mgmt Pkg'   => 'Management Liability Package',

        // Workers comp
        'WC'         => 'Workers\' Compensation',
        'WORK'       => 'Workers\' Compensation / Workplace Coverage',

        // Surety / crime
        'BOND'       => 'Surety Bond',
        'SURE'       => 'Surety',
        'CRIME'      => 'Crime / Employee Dishonesty',
        'CRIM'       => 'Crime',

        // Agency-specific — best-effort descriptions, flagged for review
        'NTL'        => 'Notary Liability (please verify)',
        'RECV'       => 'Receivables Coverage (please verify)',
        'PLMSC'      => 'Plumbers / Miscellaneous Professional (please verify)',
        'SCHPR'      => 'Schools / School Property (please verify)',
        'MEMBR'      => 'Membership / Member Benefits (please verify)',
        'RR'         => 'Railroad Protective Liability (please verify)',
        'VA'         => 'Variable Annuity (please verify)',
        'VAP'        => 'Vehicle Added Protection (please verify)',
        'BOPR'       => 'BOP with Property (please verify)',
        'MISC'       => 'Miscellaneous',
        'OTHER'      => 'Other / Uncategorized',
    ];

    public function up(): void
    {
        foreach ($this->descriptions as $sku => $description) {
            DB::table('products')
                ->where('sku', $sku)
                ->whereNull('description')
                ->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        DB::table('products')
            ->whereIn('sku', array_keys($this->descriptions))
            ->update(['description' => null]);
    }
};
