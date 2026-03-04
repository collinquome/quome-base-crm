<?php

namespace Webkul\WhiteLabel\Console\Commands;

use Illuminate\Console\Command;
use Webkul\WhiteLabel\Models\WhiteLabelSetting;

class ResetBrand extends Command
{
    protected $signature = 'brand:reset';

    protected $description = 'Reset white-label branding to defaults';

    public function handle()
    {
        $settings = WhiteLabelSetting::first();

        if (! $settings) {
            $this->info('No brand settings found. Nothing to reset.');
            return 0;
        }

        $settings->update([
            'app_name'          => 'CRM',
            'logo_url'          => null,
            'logo_dark_url'     => null,
            'favicon_url'       => null,
            'primary_color'     => '#1E40AF',
            'secondary_color'   => '#7C3AED',
            'accent_color'      => '#F59E0B',
            'email_sender_name' => 'CRM',
            'support_url'       => null,
            'login_bg_image'    => null,
            'custom_css'        => null,
        ]);

        $this->info('Brand reset to defaults.');
        return 0;
    }
}
