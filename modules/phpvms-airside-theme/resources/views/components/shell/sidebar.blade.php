@props(['shell'])

{{--
  The sidebar: brand, the admin-managed menu, and the pilot menu in the footer.
  `navigate-hover` puts wire:navigate.hover on every in-app item (prefetch on hover); external and new-window
  links use `href`, which the item renders as a plain anchor.
--}}
@php
    $branding = app(\App\Support\Branding::class);
    $rows = \Modules\PhpvmsAirsideTheme\Support\NavigationRows::build($shell->navigation(), request());
    $activeKeys = collect($rows)->filter(fn (array $row): bool => $row['active'])->pluck('key')->all();
@endphp

<x-ts-side-bar navigate-hover>
    <x-slot:brand>
        <a href="{{ url('/') }}" class="flex items-center gap-3 px-4 py-5" wire:navigate>
            <img src="{{ $branding->logo(52) }}" alt="" aria-hidden="true" class="size-8 object-contain" />
            <span class="truncate text-sm font-semibold">{{ $shell->appName() }}</span>
        </a>
    </x-slot:brand>

    <x-ts-side-bar.separator :text="__('ui.nav_section')" />

    @foreach (array_values($shell->navigation()) as $index => $item)
        <x-airside::shell.sidebar-item :item="$item" :key="(string) $index" :active-keys="$activeKeys" />
    @endforeach

    <x-slot:footer>
        <x-airside::shell.pilot-menu :shell="$shell" />
    </x-slot:footer>
</x-ts-side-bar>
