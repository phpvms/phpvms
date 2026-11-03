@props(['item', 'key', 'activeKeys'])

{{--
  One menu entry. A group (no url) nests its children and starts open when one
  of them is the current page. An in-app link goes through `route`, so the
  sidebar's `navigate` applies; an external or new-window link goes through
  `href`, which the item renders without wire:navigate.
--}}
@php
    ['icon' => $icon, 'children' => $children, 'isGroup' => $isGroup, 'active' => $active, 'opened' => $opened, 'inApp' => $inApp] = \Modules\PhpvmsAirsideTheme\Support\NavigationRows::entry($item, $key, $activeKeys, request()->getSchemeAndHttpHost());
@endphp

@if ($isGroup)
    <x-ts-side-bar.item :text="$item['label']" :icon="$icon" :opened="$opened">
        @foreach ($children as $index => $child)
            <x-vms::shell.sidebar-item :item="$child" :key="$key.'.'.$index" :active-keys="$activeKeys" />
        @endforeach
    </x-ts-side-bar.item>
@elseif ($item['new_window'] ?? false)
    <x-ts-side-bar.item :text="$item['label']" :href="$item['url']" :icon="$icon" :current="$active" target="_blank" rel="noopener noreferrer" />
@elseif ($inApp)
    <x-ts-side-bar.item :text="$item['label']" :route="$item['url']" :icon="$icon" :current="$active" />
@else
    <x-ts-side-bar.item :text="$item['label']" :href="$item['url']" :icon="$icon" :current="$active" />
@endif
