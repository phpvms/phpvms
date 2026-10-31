@props([
    'name' => null,
    'title' => null,
    'description' => null,
    'side' => 'right',
])

{{--
  Drawer: TallStackUI's slide. Props: name (the slide id), title, description, side (right|bottom).
  Slots: trigger (element that opens it), header (replaces the description), default, footer.
  Open it from: the trigger slot; wire:model="prop" (a Livewire boolean, through the slide's `wire`);
  JS with $tsui.open.slide(name); or the window events "airside-drawer-open" / "airside-drawer-close"
  with detail = name, which other islands already dispatch.
--}}
@php
    $model = $attributes->wire('model')->value();
    // Each unnamed drawer gets its own slide id; name drawers that live inside a Livewire
    // component, so the id survives re-renders.
    $name ??= 'drawer-'.\Illuminate\Support\Str::random(8);
@endphp

<div
    {{ $attributes->whereDoesntStartWith('wire:') }}
    x-data
    x-on:airside-drawer-open.window="if ($event.detail === @js($name)) $tsui.open.slide(@js($name))"
    x-on:airside-drawer-close.window="if ($event.detail === @js($name)) $tsui.close.slide(@js($name))"
>
    @isset($trigger)
        <span x-on:click="$tsui.open.slide(@js($name))">{{ $trigger }}</span>
    @endisset

    <x-ts-slide :id="$name" :title="$title" :wire="$model" :bottom="$side === 'bottom'" :footer="$footer ?? null">
        {{--
          TallStackUI's slide carries no dialog role and no focus trap (its modal does). The body
          wrapper supplies both; `show` is the slide's own Alpine state. The slide's close button
          sits outside the trap, but Escape still closes.
        --}}
        <div role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif x-trap.noscroll="show">
            @isset($header)
                {{ $header }}
            @elseif ($description)
                <p class="mb-4 text-sm text-dark-500 dark:text-dark-400">{{ $description }}</p>
            @endisset

            {{ $slot }}
        </div>
    </x-ts-slide>
</div>
