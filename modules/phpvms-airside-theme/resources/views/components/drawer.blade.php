@props([
    'name' => 'drawer',
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
@php($model = $attributes->wire('model')->value())

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
        @isset($header)
            {{ $header }}
        @elseif ($description)
            <p class="mb-4 text-sm text-dark-500 dark:text-dark-400">{{ $description }}</p>
        @endisset

        {{ $slot }}
    </x-ts-slide>
</div>
