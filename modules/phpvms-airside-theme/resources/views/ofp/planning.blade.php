@extends('app')

@section('title', 'SimBrief flight planning')

@php
    $flight = $planning?->flight ?? $aircraftSelection?->flight;
    $fields = $planning?->providerFields ?? [];
    $enabledOptions = ['0' => 'Disabled', '1' => 'Enabled'];
    $contingencyOptions = [
        '0' => 'None', 'auto' => 'AUTO', 'easa' => 'EASA',
        '0.03/5' => '3% or 05 MIN', '0.03/10' => '3% or 10 MIN', '0.03/15' => '3% or 15 MIN',
        '0.05/5' => '5% or 05 MIN', '0.05/10' => '5% or 10 MIN', '0.05/15' => '5% or 15 MIN',
        '0.03' => '3%', '0.05' => '5%', '0.1' => '10%', '0.15' => '15%',
        '3' => '03 MIN', '5' => '05 MIN', '10' => '10 MIN', '15' => '15 MIN',
    ];
    $reserveOptions = collect(['auto', '0', '15', '30', '45', '60', '75', '90'])
        ->mapWithKeys(fn (string $value): array => [$value => $value === 'auto' ? 'AUTO' : $value.' MIN'])
        ->all();
    $layoutOptions = [
        'lido' => 'LIDO', 'aal' => 'American Airlines', 'aca' => 'Air Canada', 'afr' => 'Air France (2012)',
        'afr2017' => 'Air France (2017)', 'awe' => 'US Airways', 'baw' => 'British Airways', 'ber' => 'Air Berlin',
        'dal' => 'Delta Air Lines', 'dlh' => 'Lufthansa', 'ein' => 'Aer Lingus', 'etd' => 'Etihad Airways',
        'ezy' => 'easyJet', 'gwi' => 'Germanwings', 'jbu' => 'JetBlue Airways', 'jza' => 'Jazz Aviation',
        'klm' => 'KLM Royal Dutch Airlines', 'qfa' => 'Qantas', 'ryr' => 'Ryanair', 'swa' => 'Southwest Airlines',
        'thy' => 'Turkish Airlines', 'uae' => 'Emirates Airline', 'ual' => 'United Airlines (2012)',
        'ual f:wz' => 'United Airlines (2018)',
    ];
    $callsignOptions = $planning ? collect($planning->callsignOptions)->mapWithKeys(fn (string $value): array => [$value => $value])->all() : [];
    $attemptData = $planning ? [
        'staticId' => $planning->attempt->staticId,
        'apiCodeUrl' => route('frontend.ofp.attempt.api-code', $planning->attempt->staticId),
        'pollUrl' => route('frontend.ofp.attempt.poll', $planning->attempt->staticId),
        'providerFields' => $planning->providerFields,
        'callsignEditable' => $planning->callsignEditable,
        'blockTime' => $flight->summary->blockTime,
    ] : null;
    $pilot = auth()->user();
@endphp

@section('content')
<x-airside::page name="simbrief-planning" label="SimBrief flight planning">
    <div @if ($attemptData) data-airside-island="simbrief-attempt" data-state="ready" @endif class="grid gap-6">
        @if ($attemptData)
            <script type="application/json">{!! json_encode($attemptData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
        @endif

        @if ($flight)
            <header class="flex flex-wrap items-center justify-between gap-4 pb-4">
                <x-airside::flight-ident
                    size="lg"
                    :callsign="$flight->summary->callsign"
                    :dpt="$flight->summary->dpt"
                    :arr="$flight->summary->arr"
                    :airline="$flight->summary->airline?->name"
                    :logo="$flight->summary->airline?->logo"
                    :aircraft="$planning ? $planning->aircraft->registration.' · '.$planning->aircraft->icaoType : null"
                />
                @if ($planning)
                    <x-ts-badge color="slate" light class="uppercase" data-role="state-badge">ready</x-ts-badge>
                @endif
            </header>
        @endif

        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.75fr)]">
            @if ($planning)
                <form class="grid min-w-0 gap-4" data-role="options" autocomplete="off" onsubmit="return false">
                    <x-airside::card title="Flight Information">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @if ($pilot)
                                <p class="text-[13px] sm:col-span-2 lg:col-span-3">
                                    <span class="font-semibold">{{ $pilot->name }}</span>
                                    <span class="font-mono">{{ $pilot->ident }}</span>
                                </p>
                            @endif

                            @if ($planning->callsignEditable)
                                <x-ofp-select name="callsign" label="ATC callsign" :options="$callsignOptions" :value="$fields['callsign'] ?? null" />
                            @else
                                <x-ofp-input name="callsign" label="ATC callsign" :value="$fields['callsign'] ?? ''" readonly />
                            @endif
                            <x-ofp-input name="orig" label="Departure airport" :value="$fields['orig'] ?? ''" readonly />
                            <x-ofp-input name="dest" label="Arrival airport" :value="$fields['dest'] ?? ''" readonly />
                            <x-ofp-input name="altn" label="Alternate airport" :value="$fields['altn'] ?? 'AUTO'" maxlength="8" />
                            <x-ofp-input name="std" label="Scheduled departure time (UTC)" :value="$planning->flight->scheduledDeparture ?? ''" readonly />
                            <x-ofp-input name="etd" label="Estimated departure time (UTC)" value="" readonly data-role="etd" />
                            <x-ofp-input name="date" label="Date of flight (UTC)" value="" readonly data-role="date" />

                            <div class="grid gap-1.5 sm:col-span-2 lg:col-span-3">
                                <label for="ofp-route" class="text-[13px] font-medium">Preferred company route</label>
                                <textarea id="ofp-route" name="route" rows="3">{{ $fields['route'] ?? '' }}</textarea>
                            </div>
                        </div>
                    </x-airside::card>

                    <x-airside::card title="Planning Options">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-data="{ stepclimbs: @js((string) ($fields['stepclimbs'] ?? '0')) }">
                            <div class="grid gap-1.5">
                                <label for="ofp-fl" class="text-[13px] font-medium">Preferred flight level</label>
                                <input id="ofp-fl" type="text" name="fl" maxlength="5" value="{{ $fields['fl'] ?? '' }}" x-bind:disabled="stepclimbs === '1'" />
                            </div>
                            <x-ofp-select name="contpct" label="Cont fuel" :options="$contingencyOptions" :value="$fields['contpct'] ?? '0.05/5'" />
                            <x-ofp-select name="resvrule" label="Reserve fuel" :options="$reserveOptions" :value="$fields['resvrule'] ?? '30'" />
                            <x-ofp-select name="find_sidstar" label="SID/STAR type" :options="['C' => 'Conventional', 'R' => 'RNAV']" :value="$fields['find_sidstar'] ?? 'R'" />
                            <x-ofp-select name="stepclimbs" label="Plan stepclimbs" :options="$enabledOptions" :value="$fields['stepclimbs'] ?? '0'" x-model="stepclimbs" />
                            <x-ofp-select name="etops" label="ETOPS planning" :options="$enabledOptions" :value="$fields['etops'] ?? '0'" />
                        </div>
                    </x-airside::card>

                    <x-airside::card title="Briefing Options">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <x-ofp-select name="planformat" label="OFP layout" :options="$layoutOptions" :value="$fields['planformat'] ?? 'lido'" />
                            <x-ofp-select name="units" label="Units" :options="['KGS' => 'KGS', 'LBS' => 'LBS']" :value="$fields['units'] ?? 'LBS'" />
                            <x-ofp-select name="navlog" label="Detailed navlog" :options="$enabledOptions" :value="$fields['navlog'] ?? '1'" />
                            <x-ofp-select name="tlr" label="Runway analysis" :options="$enabledOptions" :value="$fields['tlr'] ?? '1'" />
                            <x-ofp-select name="notams" label="Include NOTAMs" :options="$enabledOptions" :value="$fields['notams'] ?? '1'" />
                            <x-ofp-select name="firnot" label="FIR NOTAMs" :options="$enabledOptions" :value="$fields['firnot'] ?? '0'" />
                            <x-ofp-select name="maps" label="Flight maps" :options="['detail' => 'Detailed', 'simple' => 'Simple', 'none' => 'None']" :value="$fields['maps'] ?? 'detail'" />
                        </div>
                    </x-airside::card>
                </form>
            @elseif ($flight)
                <x-airside::card title="Route and schedule">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-md p-3">
                            <dt>Scheduled departure</dt>
                            <dd class="mt-1 font-mono text-sm">{{ $flight->scheduledDeparture ?? 'Not scheduled' }}</dd>
                        </div>
                        <div class="rounded-md p-3">
                            <dt>Scheduled arrival</dt>
                            <dd class="mt-1 font-mono text-sm">{{ $flight->scheduledArrival ?? 'Not scheduled' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-3 rounded-md p-3 font-mono text-sm leading-relaxed">{{ $flight->route ?: 'No route published' }}</p>
                </x-airside::card>
            @endif

            <x-airside::card
                title="Flight Plan Generation"
                description="Generate the SimBrief flight plan in a protected window. Keep this page open while the provider prepares the operational flight plan."
                aria-label="Generate OFP"
            >
                <div class="grid gap-3">
                    @if ($planning)
                        <div data-show="waiting-for-popup" hidden>
                            <x-airside::alert>SimBrief is open. phpVMS is checking for the completed briefing.</x-airside::alert>
                        </div>
                        <div data-show="polling" hidden>
                            <x-airside::loading text="Checking for the completed OFP (0/60)" data-role="polling" />
                        </div>
                        <div data-show="error" hidden>
                            <x-airside::alert kind="error"><span data-role="failure"></span></x-airside::alert>
                        </div>

                        <div class="grid gap-2">
                            <x-ts-button type="button" class="w-full" data-role="generate">Generate OFP</x-ts-button>
                            <x-ts-button type="button" color="slate" light class="w-full" data-role="retry" hidden>Check for completed OFP</x-ts-button>
                        </div>
                    @else
                        <div class="grid gap-2">
                            <x-ts-button type="button" class="w-full" disabled>Generate OFP</x-ts-button>
                        </div>
                    @endif

                    <p class="text-xs">This uses a server-owned planning attempt. Your stored SimBrief username is not required.</p>

                    @if ($planning)
                        <section class="grid gap-1 rounded-md p-3" aria-label="Selected aircraft">
                            <span>Aircraft assignment</span>
                            <strong class="font-mono text-sm">{{ $planning->aircraft->registration }} · {{ $planning->aircraft->icaoType }}</strong>
                            <span class="text-[13px]">{{ $planning->aircraft->subfleetName }} · {{ $planning->aircraft->airport?->icao }}</span>
                        </section>
                    @elseif ($aircraftSelection)
                        <livewire:ofp::aircraft-selection
                            :flight-id="$aircraftSelection->flight->summary->id"
                            :bid-id="$aircraftSelection->aircraftAssignmentUrl ? $aircraftSelection->flight->summary->bidId : null"
                        />
                    @endif
                </div>
            </x-airside::card>
        </div>
    </div>
</x-airside::page>
@endsection

@if ($attemptData)
    @section('scripts')
        @airsideVite('resources/js/islands/simbrief-attempt.js')
    @endsection
@endif
