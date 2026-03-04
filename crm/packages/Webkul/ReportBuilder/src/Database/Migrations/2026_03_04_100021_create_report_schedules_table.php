<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('report_definition_id');
            $table->string('frequency'); // daily, weekly, monthly
            $table->string('day_of_week')->nullable(); // for weekly: monday, tuesday, etc.
            $table->unsignedTinyInteger('day_of_month')->nullable(); // for monthly: 1-28
            $table->time('time_of_day')->default('08:00:00');
            $table->string('format')->default('csv'); // csv, pdf, xls
            $table->json('recipients'); // array of email addresses
            $table->string('subject')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->foreign('report_definition_id')
                ->references('id')
                ->on('report_definitions')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('is_active');
            $table->index('next_run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
