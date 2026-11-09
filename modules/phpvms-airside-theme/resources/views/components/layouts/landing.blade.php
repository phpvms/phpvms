@props(['title' => null])

{{--
  The landing layout: a public top nav and footer around the page, no sidebar.
  Slots: default (page), head (extra <head> markup), scripts (page scripts).
  ShellData is request-scoped, so @inject hands every render the current
  request's instance (Octane-safe: nothing holds the request across requests).
--}}
@inject('shell', 'App\Features\Shell\ShellData')
@php
    $branding = app(\App\Support\Branding::class);
    $user = $shell->user();
    $rows = collect(\Modules\PhpvmsAirsideTheme\Support\NavigationRows::build($shell->navigation(), request()))
        ->filter(fn (array $row): bool => $row['depth'] === 0 && ! $row['group']);
    $origin = request()->getSchemeAndHttpHost();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-airside-layout="{{ theme_option('layout') }}" x-data="tallstackui_darkTheme({ default: 'system' })" x-bind:class="{ 'dark': darkTheme }">

<head>
    @include('partials.head', ['title' => $title])
    {{ $head ?? '' }}
</head>

<body class="vms-landing antialiased">
    <header class="vms-landing-nav grid items-center gap-4 px-5 py-5 md:px-11 md:py-6">
        <a href="{{ url('/') }}" class="vms-landing-brand flex items-center gap-3" wire:navigate>
            <img src="{{ $branding->logo(52) }}" alt="" aria-hidden="true">
            <span>{{ $shell->appName() }}</span>
        </a>

        <nav class="vms-landing-links flex items-center gap-8" aria-label="Main">
            @foreach ($rows as $row)
                @php($item = $row['item'])
                @if ($item['new_window'] ?? false)
                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" @if ($row['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @elseif ($row['path'] !== null)
                    <a href="{{ $item['url'] }}" wire:navigate @if ($row['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @else
                    <a href="{{ $item['url'] }}" @if ($row['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endif
            @endforeach
        </nav>

        <div class="vms-landing-account flex items-center justify-end gap-4">
            @if ($user)
                <a href="{{ route('frontend.dashboard.index') }}" class="flex items-center gap-3" wire:navigate>
                    <span>{{ $user['name'] }}</span>
                    <x-ts-avatar :image="$user['avatar'] ?? null" :text="mb_strtoupper(mb_substr($user['name'], 0, 2))" sm />
                </a>
            @else
                <a href="{{ url('/login') }}">{{ __('ui.sign_in') }}</a>
                @if (! setting('general.disable_registrations', false))
                    <a href="{{ route('register') }}" class="vms-pill vms-pill--dark">{{ __('common.register') }}</a>
                @endif
            @endif
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="vms-landing-footer flex flex-wrap items-center justify-between gap-4 px-6 py-6">
        <span>{{ $shell->appName() }}</span>
        <span>&copy; {{ date('Y') }} &middot; Powered by <a href="https://www.phpvms.net" target="_blank" rel="noopener">phpvms</a></span>
    </footer>

    <x-vms::shell.toasts :shell="$shell" />

    @livewireScripts

    {{ $scripts ?? '' }}

    <x-theme-footer />
</body>

</html>
