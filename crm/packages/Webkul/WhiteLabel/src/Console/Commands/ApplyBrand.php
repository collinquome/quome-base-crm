<?php

namespace Webkul\WhiteLabel\Console\Commands;

use Illuminate\Console\Command;
use Webkul\WhiteLabel\Models\WhiteLabelSetting;

class ApplyBrand extends Command
{
    protected $signature = 'brand:apply {config : Path to brand.json config file}';

    protected $description = 'Apply white-label branding from a JSON config file';

    public function handle()
    {
        $configPath = $this->argument('config');

        if (! file_exists($configPath)) {
            // Try relative to public directory
            $configPath = public_path($configPath);
        }

        if (! file_exists($configPath)) {
            $this->error("Config file not found: {$this->argument('config')}");
            return 1;
        }

        $config = json_decode(file_get_contents($configPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: '.json_last_error_msg());
            return 1;
        }

        $settings = WhiteLabelSetting::first() ?? new WhiteLabelSetting();

        $fieldMap = [
            'app_name'          => 'app_name',
            'primary_color'     => 'primary_color',
            'secondary_color'   => 'secondary_color',
            'accent_color'      => 'accent_color',
            'email_sender_name' => 'email_sender_name',
            'support_url'       => 'support_url',
            'custom_css'        => 'custom_css',
        ];

        foreach ($fieldMap as $jsonKey => $dbField) {
            if (isset($config[$jsonKey])) {
                $settings->$dbField = $config[$jsonKey];
            }
        }

        // Handle logo paths — convert relative paths to URLs
        if (isset($config['logo'])) {
            $settings->logo_url = '/'.$config['logo'];
        }
        if (isset($config['logo_dark'])) {
            $settings->logo_dark_url = '/'.$config['logo_dark'];
        }
        if (isset($config['favicon'])) {
            $settings->favicon_url = '/'.$config['favicon'];
        }

        $settings->save();

        $this->info("Brand '{$settings->app_name}' applied successfully!");
        $this->table(
            ['Setting', 'Value'],
            collect($settings->only([
                'app_name', 'primary_color', 'secondary_color', 'accent_color',
                'logo_url', 'logo_dark_url', 'favicon_url', 'email_sender_name', 'support_url',
            ]))->map(fn ($v, $k) => [$k, $v ?? '(not set)'])->values()->toArray()
        );

        return 0;
    }
}
