import posthog from 'posthog-js';

/**
 * PostHog browser plugin.
 *
 * Reads configuration from meta tags rendered by the admin layout
 * (so it stays in sync with the server-side POSTHOG_* env vars),
 * initialises posthog-js with autocapture + pageview tracking +
 * session recording, and identifies the signed-in user so
 * browser-side and server-side events land under one distinct id.
 *
 * No-ops silently when:
 *   - the meta tags are missing (public / anonymous pages)
 *   - POSTHOG_DISABLED=true (development)
 *   - the project token is blank
 */
function readMeta(name) {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el ? el.getAttribute('content') : null;
}

function boot() {
    const token = readMeta('posthog-token');
    const host = readMeta('posthog-host') || 'https://us.i.posthog.com';
    const disabled = readMeta('posthog-disabled') === 'true';

    if (! token || disabled) {
        return null;
    }

    posthog.init(token, {
        api_host: host,
        // Capture pageviews on route transitions + every full-page load.
        capture_pageview: true,
        capture_pageleave: true,
        // Autocapture clicks / form submissions / input changes on the admin
        // UI. Excludes elements marked data-ph-no-capture so we can opt
        // sensitive fields out later without ripping this out.
        autocapture: {
            dom_event_allowlist: ['click', 'change', 'submit'],
            css_selector_allowlist: ['button', 'a', 'input', 'select', 'textarea'],
        },
        // Session recording — on, with input masking for privacy.
        session_recording: {
            maskAllInputs: true,
            maskInputOptions: {
                password: true,
                email: false,
            },
        },
        disable_session_recording: false,
        // Respect Do Not Track headers.
        respect_dnt: true,
        // Don't persist cross-subdomain cookies — each CRM install is isolated.
        persistence: 'localStorage+cookie',
        // Log to console only in dev, quiet in prod.
        loaded: (ph) => {
            const userId = readMeta('posthog-user-id');
            const userEmail = readMeta('posthog-user-email');
            const userName = readMeta('posthog-user-name');

            if (userId) {
                ph.identify('user_' + userId, {
                    email: userEmail,
                    name: userName,
                });
            }
        },
    });

    // Surface on window for ad-hoc captures from Vue templates or
    // debugging — e.g. `window.posthog?.capture('custom_event', {...})`.
    window.posthog = posthog;

    return posthog;
}

const plugin = {
    install(app) {
        const instance = boot();

        if (instance) {
            app.config.globalProperties.$posthog = instance;
        }
    },
};

export default plugin;
