<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('call_logs')) {
            Schema::create('call_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('activity_id')->nullable();
                $table->unsignedInteger('contact_id');
                $table->string('phone_number');
                $table->string('direction')->default('outbound'); // outbound, inbound
                $table->string('voip_provider'); // twilio, vonage, plivo
                $table->string('call_sid')->nullable()->index();
                $table->string('status')->default('initiated');
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->string('recording_url')->nullable();
                $table->timestamps();

                $table->foreign('activity_id')->references('id')->on('activities')->onDelete('set null');
                $table->foreign('contact_id')->references('id')->on('persons')->onDelete('cascade');
                $table->index(['contact_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
