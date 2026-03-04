<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_stage_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('stage_id');
            $table->unsignedInteger('pipeline_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();

            $table->foreign('lead_id')
                ->references('id')
                ->on('leads')
                ->onDelete('cascade');

            $table->index(['lead_id', 'stage_id']);
            $table->index(['pipeline_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_stage_history');
    }
};
