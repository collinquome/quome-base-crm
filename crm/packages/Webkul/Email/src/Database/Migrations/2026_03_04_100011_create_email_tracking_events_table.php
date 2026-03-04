<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_tracking_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('email_id');
            $table->string('event_type'); // open, click
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('url_clicked')->nullable(); // only for click events
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('email_id')
                ->references('id')
                ->on('emails')
                ->onDelete('cascade');

            $table->index(['email_id', 'event_type']);
            $table->index('created_at');
        });

        // Add tracking_id to emails table for pixel/link tracking
        Schema::table('emails', function (Blueprint $table) {
            $table->uuid('tracking_id')->nullable()->after('reference_ids');
            $table->index('tracking_id');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['tracking_id']);
            $table->dropColumn('tracking_id');
        });

        Schema::dropIfExists('email_tracking_events');
    }
};
