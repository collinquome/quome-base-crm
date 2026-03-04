<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_syncs')) {
            Schema::create('calendar_syncs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('activity_id');
                $table->string('provider'); // google, outlook
                $table->string('external_id')->nullable();
                $table->string('external_link')->nullable();
                $table->string('sync_direction')->default('outbound');
                $table->timestamps();

                $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');
                $table->index(['activity_id', 'provider']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_syncs');
    }
};
