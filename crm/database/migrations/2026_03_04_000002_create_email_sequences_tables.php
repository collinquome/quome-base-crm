<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_sequences')) {
            Schema::create('email_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('draft'); // draft, active, paused, archived
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('email_sequence_steps')) {
            Schema::create('email_sequence_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sequence_id');
                $table->integer('position')->default(0);
                $table->string('subject');
                $table->text('body');
                $table->integer('delay_days')->default(0); // days after previous step
                $table->integer('delay_hours')->default(0); // hours after previous step
                $table->timestamps();

                $table->foreign('sequence_id')->references('id')->on('email_sequences')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('email_sequence_enrollments')) {
            Schema::create('email_sequence_enrollments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sequence_id');
                $table->unsignedInteger('person_id');
                $table->string('status')->default('active'); // active, completed, stopped, replied
                $table->integer('current_step')->default(0);
                $table->timestamp('next_send_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('stopped_at')->nullable();
                $table->timestamps();

                $table->foreign('sequence_id')->references('id')->on('email_sequences')->onDelete('cascade');
                $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
                $table->unique(['sequence_id', 'person_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sequence_enrollments');
        Schema::dropIfExists('email_sequence_steps');
        Schema::dropIfExists('email_sequences');
    }
};
