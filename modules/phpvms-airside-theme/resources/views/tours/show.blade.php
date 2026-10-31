@extends('app')

@section('title', $tour->name)

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
@endphp

@section('content')
<x-vms::page name="tour" :label="$tour->name">
    <x-vms::page.header headline="Flights · Tours" :title="$tour->name">
        @if ($tour->description)
            <p class="max-w-prose text-[13px]">{{ $tour->description }}</p>
        @endif
        @if ($window)
            <p class="font-mono text-xs">{{ $window }}</p>
        @endif

        <x-slot:links>
            @if ($status)
                <x-ts-badge :color="\Modules\PhpvmsAirsideTheme\Support\StateColor::tallstack($status[0])" light :data-state="$status[0]">{{ $status[1] }}</x-ts-badge>
            @endif
            <a href="{{ route('frontend.tours.index') }}" class="text-xs font-semibold hover:underline" wire:navigate><span aria-hidden="true">← </span>All tours</a>
        </x-slot:links>
    </x-vms::page.header>

    @if ($tour->image)
        <img src="{{ $tour->image }}" alt="{{ $tour->name }}" class="max-h-[280px] w-full rounded-md object-cover" />
    @endif

    <x-ts-card header="Legs">
        @if (count($tour->legs))
            <x-vms::tours.leg-timeline :tour="$tour" />
        @else
            <p class="text-sm">This tour's legs are still being set up.</p>
        @endif
    </x-ts-card>

    <p class="text-sm"><a href="{{ route('frontend.flights.bids') }}" class="hover:underline" wire:navigate>Back to my bids</a></p>
</x-vms::page>
@endsection
