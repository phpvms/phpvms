@extends('app')

@section('title', 'SimBrief flight planning')

@php
    $flight = $planning?->flight ?? $aircraftSelection?->flight;
    $attemptData = $planning ? [
        'staticId' => $planning->attempt->staticId,
        'apiCodeUrl' => route('frontend.ofp.attempt.api-code', $planning->attempt->staticId),
        'pollUrl' => route('frontend.ofp.attempt.poll', $planning->attempt->staticId),
        'providerFields' => $planning->providerFields,
        'callsignEditable' => $planning->callsignEditable,
        'blockTime' => $flight->summary->blockTime,
        'returnUrl' => route('frontend.flights.show', $flight->summary->id),
    ] : null;
    $cancelUrl = $flight ? route('frontend.flights.show', $flight->summary->id) : route('frontend.flights.index');
@endphp

@section('content')
<x-vms::page name="simbrief-planning" label="SimBrief flight planning">
    <div @if ($attemptData) data-airside-island="simbrief-attempt" data-state="ready" @endif class="grid gap-4">
        @if ($attemptData)
            <script type="application/json">{!! json_encode($attemptData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        @endif

        <x-ts-link :href="$cancelUrl" :text="$flight ? 'Back to '.$flight->summary->callsign.' briefing' : 'Back to flight manifest'" icon="phosphor.arrow-left.light" sm class="w-fit" wire:navigate />

        <section class="vms-ofp-planning grid gap-6 rounded-(--vms-radius-lg) border border-gray-200 bg-white p-5 sm:p-7 dark:border-dark-700 dark:bg-dark-800" aria-label="Plan with SimBrief">
            @if ($flight)
                <header class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3.5">
                        <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-[14px] bg-dark-900 text-sm font-extrabold text-white dark:bg-dark-50 dark:text-dark-900">
                            @if ($flight->summary->airline?->logo)
                                <img src="{{ $flight->summary->airline->logo }}" alt="{{ $flight->summary->airline->name }}" class="size-full object-contain" />
                            @else
                                {{ $flight->summary->airline ? mb_strtoupper(mb_substr(trim($flight->summary->airline->name), 0, 2)) : 'VA' }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <h1 class="m-0 text-2xl font-extrabold tracking-tight">Plan {{ $flight->summary->callsign }} with SimBrief</h1>
                            <p class="m-0 mt-1 text-sm text-dark-500 dark:text-dark-400">
                                <span class="font-mono">{{ $flight->summary->dpt ?? '—' }} → {{ $flight->summary->arr ?? '—' }}</span>
                                @if ($planning)
                                    · <span class="font-mono">{{ $planning->aircraft->registration }}</span> {{ $planning->aircraft->icaoType }}
                                @endif
                            </p>
                        </div>
                    </div>
                    @if ($planning)
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-dark-600 uppercase dark:bg-dark-900 dark:text-dark-300" data-role="state-badge">ready</span>
                    @endif
                </header>
            @endif

            @if ($planning)
                <x-vms::ofp.planning-form :planning="$planning" />
            @elseif ($flight)
                <div class="grid gap-3">
                    <dl class="m-0 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-md p-3">
                            <dt>Scheduled departure</dt>
                            <dd class="m-0 mt-1 font-mono text-sm">{{ $flight->scheduledDeparture ?? 'Not scheduled' }}</dd>
                        </div>
                        <div class="rounded-md p-3">
                            <dt>Scheduled arrival</dt>
                            <dd class="m-0 mt-1 font-mono text-sm">{{ $flight->scheduledArrival ?? 'Not scheduled' }}</dd>
                        </div>
                    </dl>
                    <p class="m-0 rounded-md p-3 font-mono text-sm leading-relaxed">{{ $flight->route ?: 'No route published' }}</p>
                </div>
                @if ($aircraftSelection)
                    <livewire:ofp::aircraft-selection
                        :flight-id="$aircraftSelection->flight->summary->id"
                        :bid-id="$aircraftSelection->aircraftAssignmentUrl ? $aircraftSelection->flight->summary->bidId : null"
                    />
                @endif
            @endif

            @if ($planning)
                <x-vms::ofp.attempt-status />
            @endif

            <footer class="vms-ofp-planning-footer flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 pt-5 dark:border-dark-700">
                <p class="m-0 flex items-center gap-2 text-[13px] text-dark-500 dark:text-dark-400">
                    <x-ts-icon name="phosphor.info.light" class="size-4 shrink-0" />
                    <span>When SimBrief finishes, you return to the flight with the OFP's fixes in the timeline.</span>
                </p>
                <div class="flex flex-wrap gap-2">
                    <x-ts-button :href="$cancelUrl" wire:navigate color="gray" light>Cancel</x-ts-button>
                    @if ($planning)
                        <x-ts-button type="button" color="slate" light data-role="retry" hidden>Check for completed OFP</x-ts-button>
                        <x-ts-button type="button" data-role="generate">Generate OFP</x-ts-button>
                    @else
                        <x-ts-button type="button" disabled>Generate OFP</x-ts-button>
                    @endif
                </div>
            </footer>
        </section>
    </div>
</x-vms::page>
@endsection

@if ($attemptData)
    @section('scripts')
        @airsideVite('resources/js/islands/simbrief-attempt.ts')
    @endsection
@endif
