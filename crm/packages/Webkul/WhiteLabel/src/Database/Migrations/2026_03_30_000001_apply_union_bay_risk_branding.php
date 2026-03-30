<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('white_label_settings')->updateOrInsert(
            ['id' => 1],
            [
                'app_name'          => 'Union Bay Risk',
                'primary_color'     => '#1E3A5F',
                'secondary_color'   => '#2C5282',
                'accent_color'      => '#E5A100',
                'logo_url'          => '/demo-brand/logo.png',
                'logo_dark_url'     => '/demo-brand/logo-dark.png',
                'favicon_url'       => '/demo-brand/favicon.png',
                'email_sender_name' => 'Union Bay Risk',
                'support_url'       => 'https://www.unionbayrisk.com/',
                'updated_at'        => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('white_label_settings')->where('id', 1)->update([
            'app_name'          => 'CRM',
            'primary_color'     => '#1E40AF',
            'secondary_color'   => '#7C3AED',
            'accent_color'      => '#F59E0B',
            'logo_url'          => null,
            'logo_dark_url'     => null,
            'favicon_url'       => null,
            'email_sender_name' => 'CRM',
            'support_url'       => null,
        ]);
    }
};
