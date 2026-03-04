<?php

namespace Webkul\WhiteLabel\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Webkul\WhiteLabel\Repositories\WhiteLabelSettingRepository;

class WhiteLabelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'white-label');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Webkul\WhiteLabel\Console\Commands\ApplyBrand::class,
                \Webkul\WhiteLabel\Console\Commands\ResetBrand::class,
            ]);
        }

        // Share white label settings with all views
        View::composer('*', function ($view) {
            try {
                $settings = app(WhiteLabelSettingRepository::class)->getSettings();
                $view->with('whiteLabelSettings', $settings);
            } catch (\Exception $e) {
                // Table may not exist yet during migrations
            }
        });
    }

    public function register()
    {
        $this->app->singleton(WhiteLabelSettingRepository::class, function ($app) {
            return new WhiteLabelSettingRepository($app);
        });
    }
}
