<?php

namespace Webkul\ActionStream\Providers;

use Illuminate\Support\ServiceProvider;

class ActionStreamServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Webkul\ActionStream\Console\Commands\SendActionReminders::class,
            ]);
        }
    }
}
