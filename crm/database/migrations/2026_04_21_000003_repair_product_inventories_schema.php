<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair follow-up — the original create_product_inventories_table migration
     * is marked Ran in the migrations table but the table is missing from the
     * database in some environments, causing 500s on /admin/products/edit/*
     * (Product hasMany inventories eager-loads this table).
     */
    public function up(): void
    {
        if (Schema::hasTable('product_inventories')) {
            return;
        }

        Schema::create('product_inventories', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('in_stock')->default(0);
            $table->integer('allocated')->default(0);

            $table->integer('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            $table->integer('warehouse_id')->unsigned()->nullable();
            if (Schema::hasTable('warehouses')) {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            }

            $table->integer('warehouse_location_id')->unsigned()->nullable();
            if (Schema::hasTable('warehouse_locations')) {
                $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations')->onDelete('cascade');
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Repair-only — do not drop.
    }
};
