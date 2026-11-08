@props([
    'items' => [],
    'placeholder' => 'Select an option',
    'searchPlaceholder' => 'Search',
    'emptyText' => 'Nothing matches this search.',
    'disabled' => false,
])

{{--
  A searchable select that opens in place (skylight's USelectMenu). Use it where TallStackUI's styled select
  cannot go: its list teleports to the body, outside a focus-trapped drawer. Props: items (list of
  ['value', 'title', 'sub', 'meta', 'disabled' => bool]), placeholder, searchPlaceholder, emptyText, disabled.
  Choosing an item dispatches `select` with `{ value }`: x-on:select="$wire.selectSubfleet($event.detail.value)".
  Keyboard: Enter/Space/ArrowDown opens; arrows move; Enter chooses; Escape closes.
  The behaviour is airside's selectMenu (resources/js/airside/select-menu.ts).
--}}
@php
    $id = 'select-menu-'.\Illuminate\Support\Str::random(6);
@endphp
<div {{ $attributes->class('vms-select-menu relative min-w-0') }}
     x-data="selectMenu"
     x-on:keydown.escape.stop="open && close()"
     x-on:click.outside="open && close(false)">
    <button type="button"
            x-ref="button"
            class="vms-select-menu-button flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm disabled:cursor-not-allowed disabled:opacity-60"
            aria-haspopup="listbox"
            x-bind:aria-expanded="open"
            aria-controls="{{ $id }}-list"
            @disabled($disabled)
            x-on:click="toggle()"
            x-on:keydown.down.prevent="show()">
        <span class="truncate">{{ $placeholder }}</span>
        <x-ts-icon name="phosphor.caret-up-down.light" class="size-4 shrink-0" />
    </button>

    <div x-show="open" x-cloak class="vms-select-menu-panel absolute inset-x-0 top-full z-20 mt-1 grid gap-1 rounded-md p-1.5 shadow-lg">
        <input type="search"
               x-ref="search"
               x-model="query"
               x-on:input="filter()"
               x-on:keydown.down.prevent="move(1)"
               x-on:keydown.up.prevent="move(-1)"
               x-on:keydown.enter.prevent="chooseActive()"
               role="combobox"
               aria-autocomplete="list"
               aria-controls="{{ $id }}-list"
               x-bind:aria-expanded="open"
               placeholder="{{ $searchPlaceholder }}"
               aria-label="{{ $searchPlaceholder }}"
               autocomplete="off"
               class="w-full rounded-md px-2.5 py-1.5 text-sm" />
        <ul x-ref="list" id="{{ $id }}-list" role="listbox" class="m-0 grid max-h-64 list-none gap-0.5 overflow-y-auto p-0">
            @foreach ($items as $index => $item)
                <li id="{{ $id }}-{{ $index }}"
                    role="option"
                    aria-selected="false"
                    aria-disabled="{{ ($item['disabled'] ?? false) ? 'true' : 'false' }}"
                    data-value="{{ $item['value'] }}"
                    data-search="{{ mb_strtolower(implode(' ', array_filter([$item['title'] ?? '', $item['sub'] ?? '', $item['meta'] ?? '']))) }}"
                    x-on:click="choose($el)"
                    class="vms-select-menu-option flex cursor-pointer items-center justify-between gap-3 rounded-md px-2.5 py-2 aria-disabled:cursor-not-allowed aria-disabled:opacity-60">
                    <span class="grid min-w-0 gap-0.5">
                        <strong class="truncate font-mono text-[13px]">{{ $item['title'] }}</strong>
                        @if (! empty($item['sub']))
                            <span class="truncate text-xs">{{ $item['sub'] }}</span>
                        @endif
                    </span>
                    @if (! empty($item['meta']))
                        <span class="shrink-0 text-right font-mono text-[11px]">{{ $item['meta'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        <p x-ref="empty" hidden class="m-0 px-2.5 py-2 text-xs">{{ $emptyText }}</p>
    </div>
</div>
