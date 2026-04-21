<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PostHog\PostHog;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (config('posthog.api_key') && ! config('posthog.disabled')) {
            PostHog::init(
                config('posthog.api_key'),
                ['host' => config('posthog.host')]
            );
        }
    }
}
