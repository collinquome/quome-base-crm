<?php

namespace App\Services;

use PostHog\PostHog;

class PostHogService
{
    /**
     * Per-request feature flag cache so repeated checks don't round-trip PostHog.
     *
     * @var array<string, bool>
     */
    private static array $featureFlagCache = [];

    /**
     * Capture an analytics event.
     */
    public static function capture(string $distinctId, string $event, array $properties = []): void
    {
        if (config('posthog.disabled')) {
            return;
        }

        PostHog::capture([
            'distinctId'  => $distinctId,
            'event'       => $event,
            'properties'  => $properties,
        ]);
    }

    /**
     * Check whether a PostHog feature flag is enabled for the current user.
     *
     * Defaults to false when PostHog is disabled, when no API key is set, or
     * when the PostHog request throws — i.e. "flag off" is the safe default
     * so gating UI behind it never accidentally exposes a pre-release feature.
     */
    public static function isFeatureEnabled(string $flag, ?string $distinctId = null): bool
    {
        if (config('posthog.disabled') || ! config('posthog.api_key')) {
            return false;
        }

        $distinctId ??= self::distinctId();
        $cacheKey = $distinctId.':'.$flag;

        if (array_key_exists($cacheKey, self::$featureFlagCache)) {
            return self::$featureFlagCache[$cacheKey];
        }

        try {
            $result = PostHog::isFeatureEnabled($flag, $distinctId);
        } catch (\Throwable $e) {
            $result = false;
        }

        return self::$featureFlagCache[$cacheKey] = (bool) $result;
    }

    /**
     * Identify a user with their traits.
     */
    public static function identify(string $distinctId, array $properties = []): void
    {
        if (config('posthog.disabled')) {
            return;
        }

        PostHog::identify([
            'distinctId'     => $distinctId,
            'properties'     => $properties,
        ]);
    }

    /**
     * Return the distinct ID for the currently authenticated user, falling
     * back to a session-based anonymous ID when no user is logged in.
     */
    public static function distinctId(): string
    {
        $user = auth()->guard('user')->user();

        if ($user) {
            return 'user_' . $user->id;
        }

        if (! session()->has('posthog_anonymous_id')) {
            session(['posthog_anonymous_id' => (string) \Illuminate\Support\Str::uuid()]);
        }

        return session('posthog_anonymous_id');
    }
}
