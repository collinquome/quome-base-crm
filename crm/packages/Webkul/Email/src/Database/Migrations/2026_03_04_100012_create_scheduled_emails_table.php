<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_emails', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('email_id');
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed, cancelled
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('email_id')
                ->references('id')
                ->on('emails')
                ->onDelete('cascade');

            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_emails');
    }
};
