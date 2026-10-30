@props([
    'prompt' => 'Are you sure?',
    'confirmLabel' => 'Yes',
    'keepLabel' => 'Keep',
    'color' => 'red',
])

{{--
  Inline confirm ("Cancel this bid?" / "Yes, cancel it" / "Keep"). Props: prompt, confirmLabel, keepLabel,
  color (confirm button colour). Slots: trigger (the button that starts it), confirm (optional: your own
  confirm button; otherwise one is rendered and any wire:* / x-on:* attributes on the tag go to it,
  e.g. wire:click="removeBid(1)"). Alpine-only; the open state resets after confirm.
--}}
@php
    $actionAttributes = $attributes->whereStartsWith(['wire:', 'x-on:']);
@endphp
<span {{ $attributes->except(array_keys($actionAttributes->getAttributes()))->class('pv-confirm inline-flex flex-wrap items-center gap-2') }} x-data="{ asking: false }" x-on:keydown.escape="asking = false">
    <span x-show="! asking" x-on:click="asking = true">{{ $trigger ?? '' }}</span>
    <span x-show="asking" x-cloak class="inline-flex flex-wrap items-center gap-2" role="group" aria-label="{{ $prompt }}">
        <span class="text-[13px] text-ink">{{ $prompt }}</span>
        @isset($confirm)
            <span x-on:click="asking = false">{{ $confirm }}</span>
        @else
            <x-ts-button sm :color="$color" :attributes="$actionAttributes" x-on:click.capture="asking = false">{{ $confirmLabel }}</x-ts-button>
        @endisset
        <x-ts-button sm light type="button" x-on:click="asking = false">{{ $keepLabel }}</x-ts-button>
    </span>
</span>
