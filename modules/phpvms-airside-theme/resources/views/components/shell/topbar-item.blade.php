@props(['item', 'key', 'activeKeys', 'mobile' => false])

{{--
  One top bar entry, from the same menu data as the sidebar (NavigationRows::entry).
  Desktop: a link, or for a group a TallStackUI dropdown listing its links. Mobile
  (`mobile`): a plain list, groups as a label over their links, for the slide-over.
  An in-app link gets wire:navigate; an external or new-window one is a plain anchor.
--}}
@php
    ['isGroup' => $isGroup, 'active' => $active, 'opened' => $opened, 'inApp' => $inApp, 'children' => $children] = \Modules\PhpvmsAirsideTheme\Support\NavigationRows::entry($item, $key, $activeKeys, request()->getSchemeAndHttpHost());
    $newWindow = (bool) ($item['new_window'] ?? false);
    $origin = request()->getSchemeAndHttpHost();
@endphp

@if ($isGroup && $mobile)
    <li>
        <p class="vms-shell-group px-3.5 pt-3 pb-1 text-xs font-bold tracking-widest text-dark-400 uppercase">{{ $item['label'] }}</p>
        <ul class="grid gap-0.5">
            @foreach ($children as $index => $child)
                <x-vms::shell.topbar-item :item="$child" :key="$key.'.'.$index" :active-keys="$activeKeys" mobile />
            @endforeach
        </ul>
    </li>
@elseif ($isGroup)
    <li>
        <x-ts-dropdown position="bottom-start" width="sm">
            <x-slot:action>
                <button type="button" x-on:click="show = !show" class="vms-shell-link" @if ($opened) aria-current="page" @endif aria-haspopup="menu">
                    {{ $item['label'] }}
                    <x-ts-icon name="phosphor.caret-down.light" class="size-3.5 text-dark-400" />
                </button>
            </x-slot:action>

            @foreach (\Modules\PhpvmsAirsideTheme\Support\NavigationRows::leaves($children, $key) as $leaf)
                @php($leafEntry = \Modules\PhpvmsAirsideTheme\Support\NavigationRows::entry($leaf['item'], $leaf['key'], $activeKeys, $origin))
                <x-ts-dropdown.items
                    :text="$leaf['item']['label']"
                    :href="$leaf['item']['url']"
                    :navigate="$leafEntry['inApp'] && ! ($leaf['item']['new_window'] ?? false)"
                    :target="($leaf['item']['new_window'] ?? false) ? '_blank' : null"
                    :rel="($leaf['item']['new_window'] ?? false) ? 'noopener noreferrer' : null"
                    :aria-current="$leafEntry['active'] ? 'page' : null"
                />
            @endforeach
        </x-ts-dropdown>
    </li>
@else
    <li>
        <a
            href="{{ $item['url'] }}"
            class="vms-shell-link"
            @if ($active) aria-current="page" @endif
            @if ($newWindow) target="_blank" rel="noopener noreferrer" @elseif ($inApp) wire:navigate @endif
        >{{ $item['label'] }}</a>
    </li>
@endif
