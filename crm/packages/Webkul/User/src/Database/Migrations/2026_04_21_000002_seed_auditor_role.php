<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed an Auditor role — company-wide read-only.
     *
     * Intended pairing: role = Auditor, view_permission = global. The UI
     * already gates create / edit / delete buttons on the matching
     * permission keys, so a user with only view permissions sees data
     * across the company with every write control hidden.
     */
    public function up(): void
    {
        if (DB::table('roles')->where('name', 'Auditor')->exists()) {
            return;
        }

        $permissions = json_encode([
            'dashboard',
            'leads', 'leads.view',
            'quotes', 'quotes.view', 'quotes.print',
            'mail', 'mail.inbox', 'mail.sent', 'mail.view',
            'activities',
            'contacts', 'contacts.persons', 'contacts.persons.view',
            'contacts.organizations',
            'products', 'products.view',
        ]);

        DB::table('roles')->insert([
            'name'            => 'Auditor',
            'description'     => 'Read-only — sees company data but cannot create, edit, or delete',
            'permission_type' => 'custom',
            'permissions'     => $permissions,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Auditor')->delete();
    }
};
