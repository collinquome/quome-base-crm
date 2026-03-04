<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quickbooks_syncs')) {
            Schema::create('quickbooks_syncs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('contact_id');
                $table->string('qb_type'); // customer, invoice, payment
                $table->string('qb_id')->nullable(); // QuickBooks entity ID
                $table->string('qb_doc_number')->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('status')->default('created');
                $table->timestamps();

                $table->foreign('contact_id')->references('id')->on('persons')->onDelete('cascade');
                $table->index(['contact_id', 'qb_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quickbooks_syncs');
    }
};
