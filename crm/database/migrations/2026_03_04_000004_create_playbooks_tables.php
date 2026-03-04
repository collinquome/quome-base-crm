<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('playbooks')) {
            Schema::create('playbooks', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('trigger_type')->default('manual'); // manual, lead_created, stage_changed, contact_created
                $table->string('status')->default('draft'); // draft, active, archived
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('playbook_steps')) {
            Schema::create('playbook_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('playbook_id');
                $table->integer('position')->default(0);
                $table->string('action_type'); // create_activity, send_email, update_field, wait, add_tag, remove_tag
                $table->json('config')->nullable(); // action-specific configuration
                $table->integer('delay_days')->default(0);
                $table->integer('delay_hours')->default(0);
                $table->timestamps();

                $table->foreign('playbook_id')->references('id')->on('playbooks')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('playbook_executions')) {
            Schema::create('playbook_executions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('playbook_id');
                $table->string('entity_type'); // persons, leads
                $table->unsignedInteger('entity_id');
                $table->string('status')->default('running'); // running, completed, failed, cancelled
                $table->integer('current_step')->default(0);
                $table->timestamp('next_action_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('log')->nullable(); // execution log
                $table->timestamps();

                $table->foreign('playbook_id')->references('id')->on('playbooks')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('playbook_executions');
        Schema::dropIfExists('playbook_steps');
        Schema::dropIfExists('playbooks');
    }
};
