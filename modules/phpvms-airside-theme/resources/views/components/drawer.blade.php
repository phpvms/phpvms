@props([
    'name' => null,
    'title' => null,
    'description' => null,
    'side' => 'right',
])

{{--
  Drawer (UDrawer). Props: name, title, description, side (right|bottom).
  Slots: trigger (element that opens it), header (replaces title/description), default, footer.
  Open it from: the trigger slot; wire:model="prop" (Livewire entangle, two-way); or Alpine/JS by dispatching
  window event "airside-drawer-open" / "airside-drawer-close" with detail = name. Escape, backdrop click and the
  close button close it; focus returns to the opener (x-trap).
--}}
@php
    $model = $attributes->wire('model')->value();
    $panel = $side === 'bottom'
        ? 'inset-x-0 bottom-0 max-h-[85vh] rounded-t-xl border-t'
        : 'inset-y-0 right-0 w-full max-w-md border-l';
    $enter = $side === 'bottom' ? 'translate-y-full' : 'translate-x-full';
@endphp
<div
    {{ $attributes->whereDoesntStartWith('wire:')->class('pv-drawer') }}
    x-data="{ open: {{ $model ? '$wire.$entangle('.\Illuminate\Support\Js::from($model).')' : 'false' }}, name: {{ \Illuminate\Support\Js::from($name) }} }"
    x-on:airside-drawer-open.window="if ($event.detail === name) open = true"
    x-on:airside-drawer-close.window="if ($event.detail === name) open = false"
    x-on:keydown.escape.window="open = false"
>
    @isset($trigger)
        <span x-on:click="open = true">{{ $trigger }}</span>
    @endisset

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" x-on:click="open = false" aria-hidden="true"></div>
            <aside
                x-show="open"
                x-transition:enter="transition duration-200 ease-out"
                x-transition:enter-start="{{ $enter }}"
                x-transition:enter-end="translate-x-0 translate-y-0"
                x-transition:leave="transition duration-150 ease-in"
                x-transition:leave-start="translate-x-0 translate-y-0"
                x-transition:leave-end="{{ $enter }}"
                x-trap.noscroll="open"
                class="pv-drawer-panel absolute flex flex-col border-line bg-panel text-ink {{ $panel }}"
            >
                <header class="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
                    <div class="grid min-w-0 gap-1">
                        @isset($header)
                            {{ $header }}
                        @else
                            @if ($title)
                                <h2 class="text-sm font-semibold">{{ $title }}</h2>
                            @endif
                            @if ($description)
                                <p class="text-[13px] text-ink-dim">{{ $description }}</p>
                            @endif
                        @endisset
                    </div>
                    <button type="button" class="rounded-md p-1 text-ink-dim hover:bg-hover" x-on:click="open = false" aria-label="Close">
                        <x-airside::icon name="x-mark" />
                    </button>
                </header>
                <div class="scroll-thin min-h-0 flex-1 overflow-y-auto px-4 py-3">{{ $slot }}</div>
                @isset($footer)
                    <footer class="flex flex-wrap justify-end gap-2 border-t border-line bg-panel-inset px-4 py-3">{{ $footer }}</footer>
                @endisset
            </aside>
        </div>
    </template>
</div>
