<!DOCTYPE html>

<html
    class="{{ request()->cookie('dark_mode') ? 'dark' : '' }}"
    lang="{{ app()->getLocale() }}"
    dir="{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'rtl' : 'ltr' }}"
>

<head>

    {!! view_render_event('admin.layout.head.before') !!}

    <title>{{ $title }}</title>

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
        name="currency"
        content="{{
            json_encode([
                'code'   => config('app.currency'),
                'symbol' => core()->currencySymbol(config('app.currency'))])
            }}
        "
    >

    <!-- PostHog (browser analytics). Plugin reads these and boots only when token is present + not disabled. -->
    @php
        $posthogUser = auth()->guard('user')->user();
    @endphp
    <meta name="posthog-token" content="{{ config('posthog.api_key') }}">
    <meta name="posthog-host" content="{{ config('posthog.host', 'https://us.i.posthog.com') }}">
    <meta name="posthog-disabled" content="{{ config('posthog.disabled') ? 'true' : 'false' }}">
    @if ($posthogUser)
        <meta name="posthog-user-id" content="{{ $posthogUser->id }}">
        <meta name="posthog-user-email" content="{{ $posthogUser->email }}">
        <meta name="posthog-user-name" content="{{ $posthogUser->name }}">
    @endif

    @stack('meta')

    {{
        vite()->set(['src/Resources/assets/css/app.css', 'src/Resources/assets/js/app.js'])
    }}

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap"
        rel="stylesheet"
    />

    <link
        rel="preload"
        as="image"
        href="{{ url('cache/logo/bagisto.png') }}"
    >

    @php
        $wlFavicon = \Webkul\WhiteLabel\Models\WhiteLabelSetting::first()?->favicon_url;
    @endphp
    @if ($wlFavicon)
        <link
            type="image/x-icon"
            href="{{ $wlFavicon }}"
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

    @php
        $wl = \Webkul\WhiteLabel\Models\WhiteLabelSetting::first();
        $brandColor = $wl?->primary_color ?? core()->getConfigData('general.settings.menu_color.brand_color') ?? '#0E90D9';
        $secondaryColor = $wl?->secondary_color ?? '#7C3AED';
        $accentColor = $wl?->accent_color ?? '#F59E0B';
    @endphp

    @stack('styles')

    <style>
        :root {
            --brand-color: {{ $brandColor }};
            --wl-primary-color: {{ $brandColor }};
            --wl-secondary-color: {{ $secondaryColor }};
            --wl-accent-color: {{ $accentColor }};
        }

        {!! core()->getConfigData('general.content.custom_scripts.custom_css') !!}
        @if($wl?->custom_css){!! $wl->custom_css !!}@endif
    </style>

    {!! view_render_event('admin.layout.head.after') !!}
</head>

<body class="h-full font-inter dark:bg-gray-950">
    {!! view_render_event('admin.layout.body.before') !!}

    <div
        id="app"
        class="h-full"
    >
        <!-- Flash Message Blade Component -->
        <x-admin::flash-group />

        <!-- Confirm Modal Blade Component -->
        <x-admin::modal.confirm />

        {!! view_render_event('admin.layout.content.before') !!}

        <!-- Page Header Blade Component -->
        <x-admin::layouts.header />

        <div
            class="group/container sidebar-collapsed flex gap-0"
            ref="appLayout"
        >
            <!-- Page Sidebar Blade Component -->
            <x-admin::layouts.sidebar.desktop />

            <div class="flex min-h-[calc(100vh-57px)] max-w-full flex-1 flex-col bg-gray-100 pt-3 transition-all duration-300 dark:bg-gray-950">
                <!-- Page Content Blade Component -->
                <div class="px-4 pb-6 ltr:lg:pl-[85px] rtl:lg:pr-[85px]">
                    {{ $slot }}
                </div>

                <!-- Footer -->
                <div class="mt-auto pt-6">
                    @if(core()->getConfigData('general.settings.footer.label'))
                        <div class="border-t bg-white py-5 text-center text-sm font-normal dark:border-gray-800 dark:bg-gray-900 dark:text-white max-md:py-3">
                            <p>{!! core()->getConfigData('general.settings.footer.label') !!}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

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
</body>

</html>
