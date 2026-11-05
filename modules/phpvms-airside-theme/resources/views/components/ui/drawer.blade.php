@props([
    'name' => null,
    'title' => null,
    'description' => null,
    'side' => 'right',
    'event' => null,
    'subject' => null,
])

{{--
  Drawer: TallStackUI's slide. Props: name (the slide id), title, description, side (right|bottom).
  Slots: trigger (element that opens it), header (replaces the description), default, footer.
  Open it from: the trigger slot; wire:model="prop" (a Livewire boolean, through the slide's `wire`);
  or JS with $tsui.open.slide(name).
  event (with wire:model): the drawer opens on the window event `<event>.open`, shows `opening` (true until the
  component dispatches `<event>.loaded`) to its content, and calls the component's close() when the pilot closes
  it. subject: a component property that is null once closed, so a close from the server is not sent back.
  The behaviour is airside's livewireDrawer (resources/js/airside/livewire-drawer.ts).
  The dialog role, aria-modal, label and focus trap come from the ejected slide in
  vendor/ts-ui/components/slide/main.blade.php.
--}}
@php
    $model = $attributes->wire('model')->value();
    // Each unnamed drawer gets its own slide id; name drawers that live inside a Livewire
    // component, so the id survives re-renders.
    $name ??= 'drawer-'.\Illuminate\Support\Str::random(8);
@endphp

<div
    {{ $attributes->whereDoesntStartWith('wire:') }}
    @if ($event && $model)
        x-data="livewireDrawer(@js($event), @js($model), @js($subject))"
    @else
        x-data
    @endif
>
    @isset($trigger)
        <span x-on:click="$tsui.open.slide(@js($name))">{{ $trigger }}</span>
    @endisset

    <x-ts-slide :id="$name" :title="$title" :wire="$model" :bottom="$side === 'bottom'" :footer="$footer ?? null">
        @isset($header)
            {{ $header }}
        @elseif ($description)
            <p class="mb-4 text-sm text-dark-500 dark:text-dark-400">{{ $description }}</p>
        @endisset

        {{ $slot }}
    </x-ts-slide>
</div>
