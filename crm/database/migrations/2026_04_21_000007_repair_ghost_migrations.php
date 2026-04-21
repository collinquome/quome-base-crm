<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global safety net for the "ghost migration" pattern we've been hitting:
     * rows exist in the `migrations` table for create-table migrations whose
     * tables are actually missing from the database. For each such row, we
     * delete the bookkeeping record so the next migrate run re-executes the
     * original migration and creates the table.
     *
     * Idempotent — on a correctly-configured database no rows match and this
     * is a no-op. In mixed environments (like ours, where a partial dump was
     * loaded), it unblocks lead creation, warehouse tagging, jobs queue, and
     * the other features that depend on the missing pivot/support tables.
     *
     * Note: this migration only resets the bookkeeping. The next `php artisan
     * migrate` invocation (the same one that triggered this migration, via
     * Laravel's internal loop, or the subsequent deploy step) picks up the
     * now-unrecorded migrations and runs them.
     */
    public function up(): void
    {
        if (! Schema::hasTable('migrations')) {
            return;
        }

        $db = DB::connection()->getDatabaseName();

        $actualTables = collect(DB::select(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ?',
            [$db]
        ))->pluck('t')->map(fn ($t) => strtolower($t))->all();

        $ghosts = DB::table('migrations')->pluck('migration')->filter(function ($m) use ($actualTables) {
            if (! preg_match('/create_(.+)_table/', $m, $match)) {
                return false;
            }
            return ! in_array(strtolower($match[1]), $actualTables, true);
        })->values()->all();

        if (empty($ghosts)) {
            return;
        }

        DB::table('migrations')
            ->whereIn('migration', $ghosts)
            ->delete();
    }

    public function down(): void
    {
        // Repair-only — no inverse operation makes sense.
    }
};
