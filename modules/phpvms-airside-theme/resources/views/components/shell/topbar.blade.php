@props(['shell'])

{{--
  The top bar layout's nav row: brand left, the menu centred, the account menu right,
  like the landing nav. Below md the menu moves into a slide-over opened from the
  button at the left. The menu data and the account entries are the sidebar's;
  a guest gets Sign in and Register links in place of the account menu.
--}}
@php
    $rows = \Modules\PhpvmsAirsideTheme\Support\NavigationRows::build($shell->navigation(), request());
    $activeKeys = collect($rows)->filter(fn (array $row): bool => $row['active'])->pluck('key')->all();
    $menu = array_values($shell->navigation());
@endphp

<header class="vms-shell-topbar grid grid-cols-[1fr_auto] items-center gap-4 px-(--vms-gutter) py-5 md:grid-cols-[1fr_auto_1fr] md:px-7">
    <div class="flex items-center gap-3">
        <button type="button" class="cursor-pointer md:hidden" x-on:click="$tsui.open.slide('vms-topbar-menu')" aria-label="Open menu">
            <x-ts-icon name="phosphor.list.light" class="size-6 text-dark-600 dark:text-dark-300" />
        </button>
        <x-vms::shell.brand :shell="$shell" />
    </div>

    <nav class="vms-shell-links hidden md:block" aria-label="Main">
        <ul class="flex items-center gap-0.5">
            @foreach ($menu as $index => $item)
                <x-vms::shell.topbar-item :item="$item" :key="(string) $index" :active-keys="$activeKeys" />
            @endforeach
            <x-render-hook :hook="new \App\Features\Hooks\Points\App\Slots\SlotMenuItems(\App\Features\Hooks\Points\App\Slots\SlotMenuItems::TOPBAR, auth()->user())" />
        </ul>
    </nav>

    @if ($shell->user())
        <x-vms::shell.pilot-menu :shell="$shell" topbar />
    @else
        {{-- Guests (the public live map) get the landing nav's sign-in links instead of the account menu. --}}
        <div class="vms-shell-guest flex items-center justify-end gap-2">
            <a href="{{ url('/login') }}" class="vms-shell-link">{{ __('ui.sign_in') }}</a>
            @if (! setting('general.disable_registrations', false))
                <a href="{{ route('register') }}" class="vms-pill vms-pill--dark">{{ __('common.register') }}</a>
            @endif
        </div>
    @endif
</header>

<x-ts-slide id="vms-topbar-menu" left size="sm" :title="$shell->appName()">
    <nav aria-label="Main">
        <ul class="grid gap-0.5">
            @foreach ($menu as $index => $item)
                <x-vms::shell.topbar-item :item="$item" :key="(string) $index" :active-keys="$activeKeys" mobile />
            @endforeach
            <x-render-hook :hook="new \App\Features\Hooks\Points\App\Slots\SlotMenuItems(\App\Features\Hooks\Points\App\Slots\SlotMenuItems::TOPBAR_MOBILE, auth()->user())" />
        </ul>
    </nav>
</x-ts-slide>
