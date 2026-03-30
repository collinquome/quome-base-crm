<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        // Producer role — sales team member, can manage their own leads/contacts
        $producerPermissions = json_encode([
            'dashboard',
            'leads', 'leads.create', 'leads.view', 'leads.edit',
            'quotes', 'quotes.create', 'quotes.edit', 'quotes.print',
            'mail', 'mail.inbox', 'mail.sent', 'mail.compose', 'mail.view',
            'activities', 'activities.create', 'activities.edit',
            'contacts', 'contacts.persons', 'contacts.persons.create', 'contacts.persons.edit', 'contacts.persons.view',
            'contacts.organizations', 'contacts.organizations.create', 'contacts.organizations.edit',
            'products', 'products.view',
        ]);

        if (! DB::table('roles')->where('name', 'Producer')->exists()) {
            DB::table('roles')->insert([
                'name'            => 'Producer',
                'description'     => 'Sales team member — manages own leads, contacts, and activities',
                'permission_type' => 'custom',
                'permissions'     => $producerPermissions,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        // Manager role — can view all team data, manage users, settings
        $managerPermissions = json_encode([
            'dashboard',
            'leads', 'leads.create', 'leads.view', 'leads.edit', 'leads.delete',
            'quotes', 'quotes.create', 'quotes.edit', 'quotes.print', 'quotes.delete',
            'mail', 'mail.inbox', 'mail.draft', 'mail.outbox', 'mail.sent', 'mail.trash', 'mail.compose', 'mail.view', 'mail.edit', 'mail.delete',
            'activities', 'activities.create', 'activities.edit', 'activities.delete',
            'contacts', 'contacts.persons', 'contacts.persons.create', 'contacts.persons.edit', 'contacts.persons.delete', 'contacts.persons.view',
            'contacts.organizations', 'contacts.organizations.create', 'contacts.organizations.edit', 'contacts.organizations.delete',
            'products', 'products.create', 'products.edit', 'products.delete', 'products.view',
            'settings', 'settings.user', 'settings.user.users', 'settings.user.users.create', 'settings.user.users.edit',
            'settings.user.groups', 'settings.user.groups.create', 'settings.user.groups.edit',
            'settings.user.roles',
            'settings.lead', 'settings.lead.pipelines', 'settings.lead.pipelines.create', 'settings.lead.pipelines.edit',
            'settings.lead.sources', 'settings.lead.sources.create', 'settings.lead.sources.edit',
            'settings.lead.types', 'settings.lead.types.create', 'settings.lead.types.edit',
            'settings.automation', 'settings.automation.workflows', 'settings.automation.workflows.create', 'settings.automation.workflows.edit',
            'settings.automation.email_templates', 'settings.automation.email_templates.create', 'settings.automation.email_templates.edit',
            'settings.automation.data_transfer', 'settings.automation.data_transfer.imports',
            'configuration',
        ]);

        if (! DB::table('roles')->where('name', 'Manager')->exists()) {
            DB::table('roles')->insert([
                'name'            => 'Manager',
                'description'     => 'Team manager — views all team data, manages users and settings',
                'permission_type' => 'custom',
                'permissions'     => $managerPermissions,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', ['Producer', 'Manager'])->delete();
    }
};
