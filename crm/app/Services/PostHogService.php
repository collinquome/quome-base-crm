<?php

namespace App\Services;

use PostHog\PostHog;

class PostHogService
{
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
