<?php

use App\Services\PostHogService;

if (! function_exists('feature_enabled')) {
    /**
     * Check whether a PostHog feature flag is enabled for the current user.
     * Defaults to false on error or when PostHog is disabled — "flag off" is
     * the safe default for hiding UI behind unreleased features.
     */
    function feature_enabled(string $flag, ?string $distinctId = null): bool
    {
        return PostHogService::isFeatureEnabled($flag, $distinctId);
    }
}
