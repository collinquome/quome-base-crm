<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('email_address');
            $table->string('display_name')->nullable();

            // IMAP settings
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl'); // ssl, tls, none
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable(); // encrypted via Laravel's Crypt

            // SMTP settings
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_encryption')->default('tls'); // ssl, tls, none
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable(); // encrypted via Laravel's Crypt

            // OAuth (for Gmail, Outlook)
            $table->string('provider')->nullable(); // gmail, outlook, custom
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            // Sync state
            $table->timestamp('last_sync_at')->nullable();
            $table->string('status')->default('active'); // active, disabled, error
            $table->text('last_error')->nullable();
            $table->unsignedInteger('sync_days')->default(30); // how many days of history to sync

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
