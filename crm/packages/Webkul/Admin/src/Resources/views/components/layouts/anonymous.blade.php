<!DOCTYPE html>

<html
    lang="{{ app()->getLocale() }}"
    dir="{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'rtl' : 'ltr' }}"
>

<head>
    <title>{{ $title ?? '' }}</title>

    <meta charset="UTF-8">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >
    <meta
        http-equiv="content-language"
        content="{{ app()->getLocale() }}"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="base-url"
        content="{{ url()->to('/') }}"
    >
    <meta
        name="currency-code"
        {{-- content="{{ core()->getCurrentCurrencyCode() }}" --}}
    >

    <!-- PostHog (browser analytics) — anonymous pages still benefit from pageview tracking. -->
    <meta name="posthog-token" content="{{ config('posthog.api_key') }}">
    <meta name="posthog-host" content="{{ config('posthog.host', 'https://us.i.posthog.com') }}">
    <meta name="posthog-disabled" content="{{ config('posthog.disabled') ? 'true' : 'false' }}">

    @stack('meta')

    {{
        vite()->set(['src/Resources/assets/css/app.css', 'src/Resources/assets/js/app.js'])
    }}

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    />

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap"
        rel="stylesheet"
    />

    @php
        $wlAnon = \Webkul\WhiteLabel\Models\WhiteLabelSetting::first();
        $wlAnonFavicon = $wlAnon?->favicon_url;
        $brandColor = $wlAnon?->primary_color ?? core()->getConfigData('general.settings.menu_color.brand_color') ?? '#0E90D9';
        $secondaryColor = $wlAnon?->secondary_color ?? '#7C3AED';
        $accentColor = $wlAnon?->accent_color ?? '#F59E0B';
    @endphp

    @if ($wlAnonFavicon)
        <link
            type="image/x-icon"
            href="{{ $wlAnonFavicon }}"
            rel="shortcut icon"
            sizes="16x16"
        >
    @elseif ($favicon = core()->getConfigData('general.design.admin_logo.favicon'))
        <link
            type="image/x-icon"
            href="{{ Storage::url($favicon) }}"
            rel="shortcut icon"
            sizes="16x16"
        >
    @else
        <link
            type="image/x-icon"
            href="{{ vite()->asset('images/favicon.ico') }}"
            rel="shortcut icon"
            sizes="16x16"
        />
    @endif

    @stack('styles')

    <style>
        :root {
            --brand-color: {{ $brandColor }};
            --wl-primary-color: {{ $brandColor }};
            --wl-secondary-color: {{ $secondaryColor }};
            --wl-accent-color: {{ $accentColor }};
        }

        {!! core()->getConfigData('general.content.custom_scripts.custom_css') !!}
        @if($wlAnon?->custom_css){!! $wlAnon->custom_css !!}@endif
    </style>

    {!! view_render_event('admin.layout.head') !!}
</head>

<body>
    {!! view_render_event('admin.layout.body.before') !!}

    <div id="app">
        <!-- Flash Message Blade Component -->
        <x-admin::flash-group />

        {!! view_render_event('admin.layout.content.before') !!}

        <!-- Page Content Blade Component -->
        {{ $slot }}

        {!! view_render_event('admin.layout.content.after') !!}
    </div>

    {!! view_render_event('admin.layout.body.after') !!}

    @stack('scripts')

    {!! view_render_event('admin.layout.vue-app-mount.before') !!}

    <script>
        /**
         * Load event, the purpose of using the event is to mount the application
         * after all of our `Vue` components which is present in blade file have
         * been registered in the app. No matter what `app.mount()` should be
         * called in the last.
         */
        window.addEventListener("load", function(event) {
            app.mount("#app");
        });
    </script>

    {!! view_render_event('admin.layout.vue-app-mount.after') !!}

    <script type="text/javascript">
        {!! core()->getConfigData('general.content.custom_scripts.custom_javascript') !!}
    </script>
</body>

</html>
