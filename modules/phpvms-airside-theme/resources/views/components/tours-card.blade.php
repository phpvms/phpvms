@props(['tour', 'error' => null])

{{--
  One tour as an image card (TourCard). Props: tour (TourListItemData), error (cancel failure for this card).
  Lives inside the tours::tour-list component: the cancel confirm calls cancelTour there, and the bid
  button asks the assignment drawer to open on the tour's active leg.
--}}
@php
    $format = fn (string $date): string => \Illuminate\Support\Carbon::parse($date)->format('M j, Y');
    $window = match (true) {
        $tour->startDate && $tour->endDate => $format($tour->startDate).' – '.$format($tour->endDate),
        (bool) $tour->startDate => 'From '.$format($tour->startDate),
        (bool) $tour->endDate => 'Until '.$format($tour->endDate),
        default => null,
    };
    $status = match ($tour->status) {
        'in_progress' => ['info', 'In progress · '.$tour->legsCompleted.' of '.count($tour->legs).' legs'],
        'completed' => ['success', 'Completed'],
        'cancelled' => ['gray', 'Cancelled'],
        'expired' => ['warning', 'Expired'],
        default => null,
    };
    $actionLabel = match ($tour->status) {
        'in_progress' => 'Continue tour',
        'completed' => 'Fly it again',
        default => 'Bid this tour',
    };
@endphp
<article
    {{ $attributes->class('pv-tour-card grid content-start overflow-hidden rounded-md') }}
    data-tour-id="{{ $tour->id }}"
    data-mine="{{ $tour->status !== null ? 'true' : 'false' }}"
    x-data="{ expanded: false }"
    x-show="filter === 'all' || @js($tour->status !== null)"
    aria-label="{{ $tour->name }}"
>
    <div class="relative aspect-[16/7]">
        @if ($tour->image)
            <img src="{{ $tour->image }}" alt="{{ $tour->name }}" class="size-full object-cover" loading="lazy" />
        @else
            <div class="grid size-full place-items-center"><x-airside::icon name="photo" class="size-8" /></div>
        @endif
        @if ($status)
            <x-airside::state-badge :state-color="$status[0]" :label="$status[1]" class="absolute right-2.5 top-2.5" />
        @endif
    </div>

    <div class="grid gap-3 px-4 py-3">
        <div class="grid gap-1">
            @if ($window)
                <p class="font-mono text-xs">{{ $window }}</p>
            @endif
            <h2 class="text-base font-semibold">{{ $tour->name }}</h2>
            @if ($tour->description)
                <p class="text-[13px]">{{ $tour->description }}</p>
            @endif
        </div>

        @if (count($tour->legs))
            <x-tours-leg-timeline :tour="$tour" windowed />
            @if (count($tour->legs) > 8)
                <div class="flex justify-center">
                    <x-ts-button type="button" sm color="slate" light x-on:click="expanded = ! expanded" x-bind:aria-expanded="expanded ? 'true' : 'false'">
                        <span x-text="expanded ? 'Show fewer legs' : 'Show all {{ count($tour->legs) }} legs'">Show all {{ count($tour->legs) }} legs</span>
                    </x-ts-button>
                </div>
            @endif
        @endif
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2 px-4 py-2.5">
        @if ($error)
            <p class="basis-full text-right text-[13px]" role="alert">{{ $error }}</p>
        @endif

        @if ($tour->status === 'in_progress')
            <x-airside::confirm prompt="Cancel this tour?" confirm-label="Yes, cancel it" keep-label="Keep flying" wire:click="cancelTour({{ $tour->id }})">
                <x-slot:trigger>
                    <x-ts-button type="button" sm color="slate" light>Cancel tour</x-ts-button>
                </x-slot:trigger>
            </x-airside::confirm>
        @endif

        @if ($tour->activeLegFlightId)
            <x-ts-button type="button" sm x-on:click="$dispatch('assignment-drawer.open', { flightId: '{{ $tour->activeLegFlightId }}' })">{{ $actionLabel }}</x-ts-button>
        @else
            <span class="text-[13px]">This tour's legs are still being set up.</span>
        @endif
    </div>
</article>
