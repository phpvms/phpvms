@props(['shell'])

{{--
  The top bar layout's nav row: brand left, the menu centred, the account menu right,
  like the landing nav. Below md the menu moves into a slide-over opened from the
  button at the left. The menu data and the account entries are the sidebar's.
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
        </ul>
    </nav>

    <x-vms::shell.pilot-menu :shell="$shell" topbar />
</header>

<x-ts-slide id="vms-topbar-menu" left size="sm" :title="$shell->appName()">
    <nav aria-label="Main">
        <ul class="grid gap-0.5">
            @foreach ($menu as $index => $item)
                <x-vms::shell.topbar-item :item="$item" :key="(string) $index" :active-keys="$activeKeys" mobile />
            @endforeach
        </ul>
    </nav>
</x-ts-slide>
