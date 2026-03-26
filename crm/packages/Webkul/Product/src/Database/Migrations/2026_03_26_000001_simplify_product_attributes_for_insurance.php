<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make SKU not required and not unique
        DB::table('attributes')
            ->where('code', 'sku')
            ->where('entity_type', 'products')
            ->update([
                'is_required' => 0,
                'is_unique'   => 0,
            ]);

        // Make quantity not required
        DB::table('attributes')
            ->where('code', 'quantity')
            ->where('entity_type', 'products')
            ->update([
                'is_required' => 0,
            ]);

        // Make price not required
        DB::table('attributes')
            ->where('code', 'price')
            ->where('entity_type', 'products')
            ->update([
                'is_required' => 0,
            ]);

        // Default quantity to 1 for products that don't have it set
        DB::table('products')
            ->whereNull('quantity')
            ->orWhere('quantity', 0)
            ->update(['quantity' => 1]);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('code', 'sku')
            ->where('entity_type', 'products')
            ->update([
                'is_required' => 1,
                'is_unique'   => 1,
            ]);

        DB::table('attributes')
            ->where('code', 'quantity')
            ->where('entity_type', 'products')
            ->update(['is_required' => 1]);

        DB::table('attributes')
            ->where('code', 'price')
            ->where('entity_type', 'products')
            ->update(['is_required' => 1]);
    }
};
