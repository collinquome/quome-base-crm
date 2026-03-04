<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('enrichment_results')) {
            Schema::create('enrichment_results', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('contact_id')->unique();
                $table->string('provider'); // clearbit, hunter, apollo, manual
                $table->string('email');
                $table->json('data')->nullable();
                $table->timestamp('enriched_at')->nullable();
                $table->timestamps();

                $table->foreign('contact_id')->references('id')->on('persons')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enrichment_results');
    }
};
