@props(['item', 'key', 'activeKeys'])

{{--
  One menu entry. A group (no url) nests its children and starts open when one
  of them is the current page. An in-app link goes through `route`, so the
  sidebar's `navigate` applies; an external or new-window link goes through
  `href`, which the item renders without wire:navigate.
--}}
@php
    $icon = \Modules\PhpvmsAirsideTheme\Support\NavigationIcon::tallstack($item['icon'] ?? null);
    $children = array_values($item['children'] ?? []);
    $isGroup = ($item['url'] ?? '') === '';
    $active = in_array($key, $activeKeys, true);
    $opened = collect($activeKeys)->contains(fn (string $activeKey): bool => str_starts_with($activeKey, $key.'.'));
    $inApp = \Modules\PhpvmsAirsideTheme\Support\NavigationRows::menuPath((string) ($item['url'] ?? ''), request()->getSchemeAndHttpHost()) !== null;
@endphp

@if ($isGroup)
    <x-ts-side-bar.item :text="$item['label']" :icon="$icon" :opened="$opened">
        @foreach ($children as $index => $child)
            <x-airside::shell.sidebar-item :item="$child" :key="$key.'.'.$index" :active-keys="$activeKeys" />
        @endforeach
    </x-ts-side-bar.item>
@elseif ($item['new_window'] ?? false)
    <x-ts-side-bar.item :text="$item['label']" :href="$item['url']" :icon="$icon" :current="$active" target="_blank" rel="noopener noreferrer" />
@elseif ($inApp)
    <x-ts-side-bar.item :text="$item['label']" :route="$item['url']" :icon="$icon" :current="$active" />
@else
    <x-ts-side-bar.item :text="$item['label']" :href="$item['url']" :icon="$icon" :current="$active" />
@endif
