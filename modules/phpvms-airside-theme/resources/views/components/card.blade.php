@props(['title' => null, 'description' => null, 'variant' => 'outline'])

{{--
  Card: TallStackUI's card with the kit's props. Props: title, description, variant (outline|subtle; subtle
  renders the light style). Slots: header (replaces title/description), default (body), footer.
  Any other attribute (paddingless, round, class, wire:*) goes to the card. Header and footer are handed
  over as attributes: a <x-slot> inside a Blade conditional does not compile.
--}}
@php
    $headerSlot = $header ?? null;

    if ($headerSlot === null && ($title || $description)) {
        $headerSlot = new \Illuminate\View\ComponentSlot(
            '<div class="grid gap-0.5">'
            .($title ? '<h2 class="text-sm font-semibold">'.e($title).'</h2>' : '')
            .($description ? '<p class="text-xs font-normal text-dark-500 dark:text-dark-400">'.e($description).'</p>' : '')
            .'</div>'
        );
    }
@endphp
<x-ts-card :light="$variant === 'subtle'" :header="$headerSlot" :footer="$footer ?? null" {{ $attributes }}>
    {{ $slot }}
</x-ts-card>
