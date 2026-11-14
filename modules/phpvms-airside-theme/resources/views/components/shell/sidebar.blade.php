@props(['shell'])

{{--
  The sidebar: brand, the admin-managed menu, and the pilot menu in the footer.
  `collapsible` adds the rail toggle to the layout header (TallStackUI keeps
  the open state in localStorage under `side-bar`); `navigate-hover` puts
  wire:navigate.hover on every in-app item (prefetch on hover); external and
  new-window links use `href`, which the item renders as a plain anchor.
--}}
@php
    $branding = app(\App\Support\Branding::class);
    $rows = \Modules\PhpvmsAirsideTheme\Support\NavigationRows::build($shell->navigation(), request());
    $activeKeys = collect($rows)->filter(fn (array $row): bool => $row['active'])->pluck('key')->all();
@endphp

<x-ts-side-bar navigate-hover collapsible>
    <x-slot:brand>
        <x-vms::shell.brand :shell="$shell" class="mt-2 mb-5 h-12 px-4" />
    </x-slot:brand>

    <x-slot:brand-collapsed>
        <a href="{{ url('/') }}" class="mt-2 mb-5 flex h-12 w-full items-center px-4" wire:navigate aria-label="{{ $shell->appName() }}">
            <img src="{{ $branding->logo(52) }}" alt="" aria-hidden="true" class="size-8 object-contain" />
        </a>
    </x-slot:brand-collapsed>

    @foreach (array_values($shell->navigation()) as $index => $item)
        <x-vms::shell.sidebar-item :item="$item" :key="(string) $index" :active-keys="$activeKeys" />
    @endforeach

    <x-render-hook :hook="new \App\Features\Hooks\Points\App\Slots\SlotMenuItems(\App\Features\Hooks\Points\App\Slots\SlotMenuItems::SIDEBAR, auth()->user())" />

    <x-slot:footer>
        <x-vms::shell.pilot-menu :shell="$shell" />
    </x-slot:footer>
</x-ts-side-bar>
