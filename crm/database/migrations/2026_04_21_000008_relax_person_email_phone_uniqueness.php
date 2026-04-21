<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The stock Krayin attribute seed marks the person `emails` and
     * `contact_numbers` attributes as is_unique=1, which causes the EAV layer
     * to reject creating a new person whose email or phone already exists on
     * another person. For an insurance CRM this is a blocker — multiple
     * prospects routinely share the same household phone/email.
     *
     * Relax the constraint so duplicates are allowed; the UI surfaces the
     * existing matching person separately for disambiguation.
     */
    public function up(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'persons')
            ->whereIn('code', ['emails', 'contact_numbers'])
            ->update(['is_unique' => 0]);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'persons')
            ->whereIn('code', ['emails', 'contact_numbers'])
            ->update(['is_unique' => 1]);
    }
};
