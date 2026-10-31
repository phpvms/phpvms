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
  or JS with $tsui.open.slide(name).
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
    x-data
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
