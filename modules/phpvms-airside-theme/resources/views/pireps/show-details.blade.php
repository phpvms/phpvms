{{-- PIREP side column: the FLIGHT DETAILS key/value list; empty values are left out (PirepDetails). Variable: $pirep (PirepData). --}}
@php
    $details = collect([
        ['Airline', $pirep->airline],
        ['Flight Type', $pirep->flightType],
        ['Status', $pirep->status],
        ['Source', $pirep->sourceName ? $pirep->source.' · '.$pirep->sourceName : $pirep->source],
        ['Route', $pirep->route],
        ['Planned Time', $pirep->plannedFlightTime],
        ['Planned Distance', $pirep->plannedDistance],
        ['Block Fuel', $pirep->blockFuel],
    ])->filter(fn (array $detail): bool => filled($detail[1]));
@endphp
<aside class="pv-pirep-details grid content-start gap-3">
    <div class="rounded-md border border-line bg-panel px-3.5 py-3">
        <p class="pv-eyebrow">FLIGHT DETAILS</p>
        <dl class="m-0 mt-2 grid grid-cols-[auto_1fr] gap-x-3.5 gap-y-1.5 text-xs">
            @foreach ($details as [$label, $value])
                <dt class="whitespace-nowrap text-ink-faint">{{ $label }}</dt>
                <dd class="m-0 text-right text-ink">{{ $value }}</dd>
            @endforeach
        </dl>
    </div>
</aside>
