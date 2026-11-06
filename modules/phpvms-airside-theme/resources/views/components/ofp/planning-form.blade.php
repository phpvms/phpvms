@props(['planning'])

{{--
  The SimBrief planning form: the Flight information, Planning options and Briefing options fieldsets, with every
  provider field name. Used by the planning page and by the planning drawer on the flight page; the simbrief-attempt
  island (resources/js/islands/simbrief-attempt.ts) reads it through [data-role="options"].
  Props: planning (a SimBriefPlanningData).
--}}
@php
    $fields = $planning->providerFields;
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
    $callsignOptions = collect($planning->callsignOptions)->mapWithKeys(fn (string $value): array => [$value => $value])->all();
    $pilot = auth()->user();
@endphp
<form {{ $attributes->class('grid min-w-0') }} data-role="options" autocomplete="off" onsubmit="return false">
    <fieldset class="vms-ofp-planning-fieldset m-0 grid min-w-0 gap-4 border-0 border-t border-gray-200 p-0 py-6 first:border-t-0 first:pt-0 last:pb-0 md:grid-cols-[14rem_minmax(0,1fr)] md:gap-8 dark:border-dark-700" aria-labelledby="ofp-group-flight">
        <div class="vms-ofp-planning-fieldset-intro min-w-0">
            <p id="ofp-group-flight" class="m-0 mb-1 text-[15px] font-extrabold">Flight information</p>
            <p class="m-0 clear-both text-[13px] text-gray-500 dark:text-dark-400">Callsign, airports, times and the company route sent to SimBrief.</p>
        </div>
        <div class="vms-ofp-planning-fieldset-fields grid min-w-0 content-start gap-4 sm:grid-cols-2">
        @if ($pilot)
            <p class="m-0 text-[13px] sm:col-span-2">
                <span class="font-semibold">{{ $pilot->name }}</span>
                <span class="font-mono">{{ $pilot->ident }}</span>
            </p>
        @endif

        @if ($planning->callsignEditable)
            <x-vms::ofp.select name="callsign" label="ATC callsign" :options="$callsignOptions" :value="$fields['callsign'] ?? null" />
        @else
            <x-vms::ofp.input name="callsign" label="ATC callsign" :value="$fields['callsign'] ?? ''" readonly />
        @endif
        <x-vms::ofp.input name="orig" label="Departure airport" :value="$fields['orig'] ?? ''" readonly />
        <x-vms::ofp.input name="dest" label="Arrival airport" :value="$fields['dest'] ?? ''" readonly />
        <x-vms::ofp.input name="altn" label="Alternate airport" :value="$fields['altn'] ?? 'AUTO'" maxlength="8" />
        <x-vms::ofp.input name="std" label="Scheduled departure time (UTC)" :value="$planning->flight->scheduledDeparture ?? ''" readonly />
        <x-vms::ofp.input name="etd" label="Estimated departure time (UTC)" value="" readonly data-role="etd" />
        <x-vms::ofp.input name="date" label="Date of flight (UTC)" value="" readonly data-role="date" />

        <div class="sm:col-span-2">
            <x-ts-textarea label="Preferred company route" id="ofp-route" name="route" rows="3" class="font-mono text-[12.5px]" :value="$fields['route'] ?? ''" />
        </div>
        </div>
    </fieldset>

    <fieldset class="vms-ofp-planning-fieldset m-0 grid min-w-0 gap-4 border-0 border-t border-gray-200 p-0 py-6 first:border-t-0 first:pt-0 last:pb-0 md:grid-cols-[14rem_minmax(0,1fr)] md:gap-8 dark:border-dark-700" aria-labelledby="ofp-group-planning" x-data="{ stepclimbs: @js((string) ($fields['stepclimbs'] ?? '0')) }">
        <div class="vms-ofp-planning-fieldset-intro min-w-0">
            <p id="ofp-group-planning" class="m-0 mb-1 text-[15px] font-extrabold">Planning options</p>
            <p class="m-0 clear-both text-[13px] text-gray-500 dark:text-dark-400">Flight level, fuel policy and routing preferences.</p>
        </div>
        <div class="vms-ofp-planning-fieldset-fields grid min-w-0 content-start gap-4 sm:grid-cols-2">
        <x-ts-input label="Preferred flight level" id="ofp-fl" name="fl" maxlength="5" :value="$fields['fl'] ?? ''" x-bind:disabled="stepclimbs === '1'" />
        <x-vms::ofp.select name="contpct" label="Cont fuel" :options="$contingencyOptions" :value="$fields['contpct'] ?? '0.05/5'" />
        <x-vms::ofp.select name="resvrule" label="Reserve fuel" :options="$reserveOptions" :value="$fields['resvrule'] ?? '30'" />
        <x-vms::ofp.select name="find_sidstar" label="SID/STAR type" :options="['C' => 'Conventional', 'R' => 'RNAV']" :value="$fields['find_sidstar'] ?? 'R'" />
        <x-vms::ofp.select name="stepclimbs" label="Plan stepclimbs" :options="$enabledOptions" :value="$fields['stepclimbs'] ?? '0'" x-model="stepclimbs" />
        <x-vms::ofp.select name="etops" label="ETOPS planning" :options="$enabledOptions" :value="$fields['etops'] ?? '0'" />
        </div>
    </fieldset>

    <fieldset class="vms-ofp-planning-fieldset m-0 grid min-w-0 gap-4 border-0 border-t border-gray-200 p-0 py-6 first:border-t-0 first:pt-0 last:pb-0 md:grid-cols-[14rem_minmax(0,1fr)] md:gap-8 dark:border-dark-700" aria-labelledby="ofp-group-briefing">
        <div class="vms-ofp-planning-fieldset-intro min-w-0">
            <p id="ofp-group-briefing" class="m-0 mb-1 text-[15px] font-extrabold">Briefing options</p>
            <p class="m-0 clear-both text-[13px] text-gray-500 dark:text-dark-400">Layout, units and what the briefing includes.</p>
        </div>
        <div class="vms-ofp-planning-fieldset-fields grid min-w-0 content-start gap-4 sm:grid-cols-2">
        <x-vms::ofp.select name="planformat" label="OFP layout" :options="$layoutOptions" :value="$fields['planformat'] ?? 'lido'" />
        <x-vms::ofp.select name="units" label="Units" :options="['KGS' => 'KGS', 'LBS' => 'LBS']" :value="$fields['units'] ?? 'LBS'" />
        <x-vms::ofp.select name="navlog" label="Detailed navlog" :options="$enabledOptions" :value="$fields['navlog'] ?? '1'" />
        <x-vms::ofp.select name="tlr" label="Runway analysis" :options="$enabledOptions" :value="$fields['tlr'] ?? '1'" />
        <x-vms::ofp.select name="notams" label="Include NOTAMs" :options="$enabledOptions" :value="$fields['notams'] ?? '1'" />
        <x-vms::ofp.select name="firnot" label="FIR NOTAMs" :options="$enabledOptions" :value="$fields['firnot'] ?? '0'" />
        <x-vms::ofp.select name="maps" label="Flight maps" :options="['detail' => 'Detailed', 'simple' => 'Simple', 'none' => 'None']" :value="$fields['maps'] ?? 'detail'" />
        </div>
    </fieldset>
</form>
