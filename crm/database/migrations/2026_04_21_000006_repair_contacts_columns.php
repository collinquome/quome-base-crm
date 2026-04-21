<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persons and organizations are both missing columns from their respective
     * original migrations. These 500 when creating leads (persons.unique_id
     * insert), filtering contacts (persons.user_id where-clause), and other
     * flows. Idempotent and no-op on correctly-configured environments.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('persons', 'job_title')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->string('job_title')->nullable();
            });
        }

        if (! Schema::hasColumn('persons', 'user_id')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->integer('user_id')->unsigned()->nullable();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }

        if (! Schema::hasColumn('persons', 'unique_id')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->string('unique_id')->nullable()->unique();
            });

            // Backfill using the same rule the original migration used.
            $tableName = DB::getTablePrefix().'persons';
            DB::statement("
                UPDATE {$tableName}
                SET unique_id = CONCAT(
                    COALESCE(user_id, ''), '|',
                    COALESCE(organization_id, ''), '|',
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(emails, '$[0].value')), ''), '|',
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(contact_numbers, '$[0].value')), '')
                )
            ");
        }

        if (! Schema::hasColumn('organizations', 'user_id')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->integer('user_id')->unsigned()->nullable();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        // Repair-only — do not drop.
    }
};
