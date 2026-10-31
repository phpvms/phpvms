@props(['label', 'items' => [], 'action', 'searchPlaceholder' => 'Search', 'emptyText' => 'Nothing matches this search.', 'selected' => null])

{{--
  A searchable list of choices for the assignment drawer. Props: label, items (list of ['value' => int, 'title',
  'sub', 'meta', 'disabled' => bool]), action (the Livewire method each choice calls with its value), searchPlaceholder,
  emptyText, selected (the chosen value, if any). The filter runs in the browser over the rendered rows.
--}}
<div {{ $attributes->class('grid min-w-0 gap-2') }} x-data="{ q: '' }">
    <span class="text-[13px] font-semibold">{{ $label }}</span>
    <input type="search" x-model="q" placeholder="{{ $searchPlaceholder }}" autocomplete="off" aria-label="{{ $searchPlaceholder }}" />
    <ul class="m-0 grid max-h-64 list-none gap-1.5 overflow-y-auto p-0" aria-label="{{ $label }}">
        @foreach ($items as $item)
            <li
                wire:key="{{ $action }}-{{ $item['value'] }}"
                data-search="{{ mb_strtolower(($item['title'] ?? '').' '.($item['sub'] ?? '')) }}"
                x-show="$el.dataset.search.includes(q.trim().toLowerCase())"
            >
                <button
                    type="button"
                    wire:click="{{ $action }}({{ $item['value'] }})"
                    @disabled($item['disabled'] ?? false)
                    @class([
                        'flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left disabled:cursor-not-allowed disabled:opacity-60',
                    ])
                >
                    <span class="grid min-w-0">
                        <strong class="truncate text-[13px]">{{ $item['title'] }}</strong>
                        @if (! empty($item['sub']))
                            <span class="truncate text-xs">{{ $item['sub'] }}</span>
                        @endif
                    </span>
                    <span class="shrink-0 font-mono text-[11px]">{{ $item['meta'] ?? '' }}</span>
                </button>
            </li>
        @endforeach
    </ul>
    <p x-show="q.trim() !== '' && ! [...$root.querySelectorAll('li[data-search]')].some((row) => row.dataset.search.includes(q.trim().toLowerCase()))" x-cloak class="text-xs">{{ $emptyText }}</p>
</div>
