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
<x-airside::page name="simbrief-briefing" label="SimBrief briefing">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-line pb-4">
        <x-airside::flight-ident
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

    <x-airside::card title="Route">
        <div data-airside-island="briefing-globe" class="min-w-0">
            <script type="application/json">{!! json_encode($globeData, $jsonFlags) !!}</script>
            <div data-role="map" class="h-[280px] overflow-hidden rounded-md bg-panel-inset"></div>
            <div data-role="empty" hidden class="grid min-h-[180px] place-items-center rounded-md border border-dashed border-line bg-panel-inset p-6 text-center text-ink-dim" role="status">
                Route map unavailable because airport coordinates are missing.
            </div>
        </div>
    </x-airside::card>

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.75fr)]">
        <x-airside::card title="Operational summary">
            <div class="grid gap-3">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md border border-line bg-panel-inset p-3">
                        <dt class="micro">Flight</dt>
                        <dd class="mt-1 font-mono text-sm">{{ $flight->summary->callsign }}</dd>
                    </div>
                    <div class="rounded-md border border-line bg-panel-inset p-3">
                        <dt class="micro">Aircraft</dt>
                        <dd class="mt-1 font-mono text-sm">{{ $briefing->aircraft->registration }}</dd>
                    </div>
                    <div class="rounded-md border border-line bg-panel-inset p-3">
                        <dt class="micro">Scheduled departure</dt>
                        <dd class="mt-1 font-mono text-sm">{{ $flight->scheduledDeparture ?? 'Not scheduled' }}</dd>
                    </div>
                    <div class="rounded-md border border-line bg-panel-inset p-3">
                        <dt class="micro">Scheduled arrival</dt>
                        <dd class="mt-1 font-mono text-sm">{{ $flight->scheduledArrival ?? 'Not scheduled' }}</dd>
                    </div>
                </dl>

                <p class="micro">ATC plan</p>
                <pre class="ofp-text">{{ $briefing->atcPlan ?: 'No ATC flight plan published.' }}</pre>

                <p class="micro">Weather</p>
                <dl class="grid gap-3 sm:grid-cols-2">
                    @foreach ($weather as $label => $text)
                        <div class="rounded-md border border-line bg-panel-inset p-3">
                            <dt class="micro">{{ $label }}</dt>
                            <dd class="mt-1 overflow-wrap-anywhere font-mono text-xs">{{ $text ?: 'Not available' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </x-airside::card>

        <livewire:ofp::briefing-actions :briefing-id="$briefing->id" />
    </div>

    <x-airside::card title="Text OFP">
        <div class="grid gap-3">
            @if (count($briefing->textSections))
                <div x-data="{ tab: 0 }" class="grid gap-3">
                    <div class="flex flex-wrap gap-1 border-b border-line" role="tablist" aria-label="Text OFP sections">
                        @foreach ($briefing->textSections as $index => $section)
                            <button
                                type="button"
                                role="tab"
                                class="-mb-px border-b-2 px-3 py-1.5 text-[13px] font-medium"
                                x-bind:class="tab === {{ $index }} ? 'border-accent text-accent' : 'border-transparent text-ink-dim hover:text-ink'"
                                x-bind:aria-selected="tab === {{ $index }} ? 'true' : 'false'"
                                x-on:click="tab = {{ $index }}"
                            >{{ $section['title'] }}</button>
                        @endforeach
                    </div>
                    @foreach ($briefing->textSections as $index => $section)
                        {{-- Sanitised server-side by App\Support\SimBriefPlanHtml (allowlisted tags and attributes, script subtrees dropped, non-http URLs stripped). --}}
                        <pre class="ofp-text" role="tabpanel" x-show="tab === {{ $index }}" @if ($index > 0) x-cloak @endif>{!! $section['html'] !!}</pre>
                    @endforeach
                </div>
            @else
                <pre class="ofp-text">No text OFP is available.</pre>
            @endif

            @if (count($briefing->images))
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($briefing->images as $image)
                        <a href="{{ $image['url'] }}" target="_blank" rel="noopener" class="overflow-hidden rounded-md border border-line">
                            <img src="{{ $image['url'] }}" alt="{{ $image['name'] }}" class="w-full" loading="lazy" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-airside::card>
</x-airside::page>
@endsection

@section('scripts')
    @airsideVite('resources/js/islands/briefing-globe.js')
@endsection
