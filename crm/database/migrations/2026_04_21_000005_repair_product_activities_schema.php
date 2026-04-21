<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Another repair follow-up — create_product_activities_table is marked
     * Ran in the migrations table but the table is missing in some
     * environments, 500-ing /admin/products/{id}/activities.
     */
    public function up(): void
    {
        if (Schema::hasTable('product_activities')) {
            return;
        }

        Schema::create('product_activities', function (Blueprint $table) {
            $table->integer('activity_id')->unsigned();
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');

            $table->integer('product_id')->unsigned();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Repair-only — do not drop.
    }
};
