<x-admin::layouts.anonymous>
    <!-- Page Title -->
    <x-slot:title>
        @lang("admin::app.errors.{$errorCode}.title")
    </x-slot>

    @php
        $wlSettings = \Webkul\WhiteLabel\Models\WhiteLabelSetting::first();
        $wlLogo = request()->cookie('dark_mode') ? ($wlSettings?->logo_dark_url ?? $wlSettings?->logo_url) : $wlSettings?->logo_url;
        $wlAppName = $wlSettings?->app_name ?? config('app.name');
        $supportUrl = $wlSettings?->support_url;
    @endphp

    <!-- Error page Information -->
	<div class="flex h-[100vh] items-center justify-center bg-white dark:bg-gray-900">
        <div class="flex max-w-[745px] items-center gap-5">
            <div class="flex w-full flex-col gap-6">
                @if($wlLogo)
                    <img
                        src="{{ $wlLogo }}"
                        alt="{{ $wlAppName }}"
                        class="h-10 w-auto ltr:pr-16 rtl:pl-16"
                    >
                @else
                    <span class="text-xl font-bold text-gray-800 dark:text-white ltr:pr-16 rtl:pl-16">
                        {{ $wlAppName }}
                    </span>
                @endif

				<div class="text-[38px] font-bold text-gray-800 dark:text-white">
                    {{ $errorCode }}
                </div>

                <p class="text-sm text-gray-800 dark:text-white">
                    @lang("admin::app.errors.{$errorCode}.description")
                </p>

                <div class="flex items-center gap-2.5">
                    <a
                        href="{{ url()->previous() }}"
                        class="cursor-pointer text-sm font-semibold text-blue-600 transition-all hover:underline"
                    >
                        @lang('admin::app.errors.go-back')
                    </a>

                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="6" height="7" viewBox="0 0 6 7" fill="none">
                            <circle cx="3" cy="3.5" r="3" fill="#9CA3AF"/>
                        </svg>
                    </span>

                    <a
                        href="{{ route('admin.dashboard.index') }}"
                        class="text-sm font-semibold text-blue-600 transition-all hover:underline"
                    >
                        @lang('admin::app.errors.dashboard')
                    </a>
                </div>

                @if($supportUrl)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        If the problem persists, please
                        <a href="{{ $supportUrl }}" class="font-semibold text-blue-600 transition-all hover:underline">contact support</a>.
                    </p>
                @endif
            </div>

            <div class="w-full">
                <img src="{{ vite()->asset('images/error.svg') }}" />
            </div>
        </div>
	</div>
</x-admin::layouts.anonymous>
