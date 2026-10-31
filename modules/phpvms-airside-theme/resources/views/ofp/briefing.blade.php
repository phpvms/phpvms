@extends('app')
@inject('shell', 'App\Features\Shell\ShellData')

@section('title', 'SimBrief briefing')

@php
    $flight = $briefing->flight;
    $globeData = [
        'map' => $shell->map(),
        'departure' => $flight->departure,
        'arrival' => $flight->arrival,
        'plannedFixes' => $briefing->plannedFixes,
    ];
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
    $weather = [
        'Departure METAR' => $briefing->weather['departureMetar'] ?? '',
        'Departure TAF' => $briefing->weather['departureTaf'] ?? '',
        'Arrival METAR' => $briefing->weather['arrivalMetar'] ?? '',
        'Arrival TAF' => $briefing->weather['arrivalTaf'] ?? '',
    ];
@endphp

@section('content')
<x-vms::page name="simbrief-briefing" label="SimBrief briefing">
    <header class="flex flex-wrap items-center justify-between gap-4 pb-4">
        <x-vms::flights.ident
            size="lg"
            :callsign="$flight->summary->callsign"
            :dpt="$flight->summary->dpt"
            :arr="$flight->summary->arr"
            :airline="$flight->summary->airline?->name"
            :logo="$flight->summary->airline?->logo"
            :aircraft="$briefing->aircraft->registration.' · '.$briefing->aircraft->icaoType"
        />
        <x-ts-badge color="slate" light class="uppercase">Completed briefing</x-ts-badge>
    </header>

    <x-ts-card header="Route">
        <div data-airside-island="briefing-globe" class="min-w-0">
            <script type="application/json">{!! json_encode($globeData, $jsonFlags) !!}</script>
            <div data-role="map" class="h-[280px] overflow-hidden rounded-md"></div>
            <div data-role="empty" hidden class="grid min-h-[180px] place-items-center rounded-md p-6 text-center" role="status">
                Route map unavailable because airport coordinates are missing.
            </div>
        </div>
    </x-ts-card>

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.75fr)]">
        <x-ts-card header="Operational summary">
            <div class="grid gap-3">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md p-3">
                        <dt>Flight</dt>
                        <dd class="mt-1 font-mono text-sm">{{ $flight->summary->callsign }}</dd>
                    </div>
                    <div class="rounded-md p-3">
                        <dt>Aircraft</dt>
                        <dd class="mt-1 font-mono text-sm">{{ $briefing->aircraft->registration }}</dd>
                    </div>
                    <div class="rounded-md p-3">
                        <dt>Scheduled departure</dt>
                        <dd class="mt-1 font-mono text-sm">{{ $flight->scheduledDeparture ?? 'Not scheduled' }}</dd>
                    </div>
                    <div class="rounded-md p-3">
                        <dt>Scheduled arrival</dt>
                        <dd class="mt-1 font-mono text-sm">{{ $flight->scheduledArrival ?? 'Not scheduled' }}</dd>
                    </div>
                </dl>

                <p>ATC plan</p>
                <pre>{{ $briefing->atcPlan ?: 'No ATC flight plan published.' }}</pre>

                <p>Weather</p>
                <dl class="grid gap-3 sm:grid-cols-2">
                    @foreach ($weather as $label => $text)
                        <div class="rounded-md p-3">
                            <dt>{{ $label }}</dt>
                            <dd class="mt-1 font-mono text-xs">{{ $text ?: 'Not available' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </x-ts-card>

        <livewire:ofp::briefing-actions :briefing-id="$briefing->id" />
    </div>

    <x-ts-card header="Text OFP">
        <div class="grid gap-3">
            @if (count($briefing->textSections))
                <x-ts-tab selected="section-0">
                    @foreach ($briefing->textSections as $index => $section)
                        <x-ts-tab.items tab="section-{{ $index }}" :title="$section['title']">
                            {{-- Sanitised server-side by App\Support\SimBriefPlanHtml (allowlisted tags and attributes, script subtrees dropped, non-http URLs stripped). --}}
                            <pre>{!! $section['html'] !!}</pre>
                        </x-ts-tab.items>
                    @endforeach
                </x-ts-tab>
            @else
                <pre>No text OFP is available.</pre>
            @endif

            @if (count($briefing->images))
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($briefing->images as $image)
                        <a href="{{ $image['url'] }}" target="_blank" rel="noopener" class="overflow-hidden rounded-md">
                            <img src="{{ $image['url'] }}" alt="{{ $image['name'] }}" class="w-full" loading="lazy" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-ts-card>
</x-vms::page>
@endsection

@section('scripts')
    @airsideVite('resources/js/islands/briefing-globe.js')
@endsection
