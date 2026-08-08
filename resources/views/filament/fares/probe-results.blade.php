{{--
    Price probe results: for a chosen flight, every resolved fare per
    subfleet with a provenance badge per field (BASE / SUBF / FLT), plus the
    auto-price formula expanded with the flight's own distance and airline
    factor when fares.auto_price is on. Rendered reactively inside the probe
    modal on the Fares list.

    Expects: $flight (?Flight, pivot relations loaded).
--}}
@php
    use App\Filament\Resources\Fares\Support\FareTrace;

    $currency = setting('units.currency');
    $autoPrice = (bool) setting('fares.auto_price');

    $sourceBadge = [
        'base'     => ['BASE', 'text-(--ink-3) border-(--line-strong)'],
        'subfleet' => ['SUBF', 'text-(--info) border-(--info-line)'],
        'flight'   => ['FLT', 'text-(--warn) border-(--warn)'],
    ];
@endphp

@if ($flight === null)
    <div class="py-6 text-center text-[12.5px] text-(--ink-3)">
        {{ __('filament.fare_probe_empty') }}
    </div>
@else
    @php
        $distanceNm = (int) round($flight->distance?->toUnit('nmi') ?? 0);
        $lowCost = (bool) $flight->airline?->low_cost;
        $lowCostFactor = $lowCost ? (float) setting('fares.low_cost_multiplier', 1) : 1.0;
        $flightFaresById = $flight->fares->keyBy('id');
    @endphp

    <div class="fare-probe">
        <div class="fare-probe__meta">
            <span class="font-mono font-semibold text-(--ink)">{{ $flight->ident }}</span>
            <span class="font-mono">{{ $flight->dpt_airport_id }} → {{ $flight->arr_airport_id }}</span>
            <span>{{ number_format($distanceNm) }} nm</span>
            <span>{{ $flight->airline?->name }}@if ($lowCost) · low-cost ×{{ $lowCostFactor }}@endif</span>
        </div>

        @forelse ($flight->subfleets as $subfleet)
            <div class="fare-probe__group">
                <div class="fare-probe__group-title">{{ $subfleet->name }} <span class="font-mono text-(--ink-4)">{{ $subfleet->type }}</span></div>

                @if ($subfleet->fares->isEmpty())
                    <div class="fare-probe__quiet">{{ __('filament.fare_probe_no_fares') }}</div>
                @else
                    <table class="fare-probe__table">
                        <thead>
                            <tr>
                                <th>{{ trans_choice('pireps.fare', 1) }}</th>
                                <th>{{ __('common.price') }}</th>
                                <th>{{ __('common.cost') }}</th>
                                <th>{{ __('common.capacity') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subfleet->fares as $fare)
                                @php
                                    $trace = FareTrace::resolve(
                                        $fare,
                                        $fare->pivot,
                                        $flightFaresById->get($fare->id)?->pivot,
                                    );
                                    $auto = $autoPrice;
                                    $autoValue = $auto
                                        ? round(max(0, ((float) $trace['base_price']['value'] + $distanceNm * (float) $trace['per_nm']['value']) * (float) $trace['multiplier']['value'] * $lowCostFactor), 2)
                                        : null;
                                @endphp
                                <tr>
                                    <td><span class="font-mono font-semibold">{{ $fare->code }}</span> {{ $fare->name }}</td>
                                    <td>
                                        @if ($auto)
                                            <span class="font-mono">{{ \Filament\Support\format_money($autoValue, $currency) }}</span>
                                            <span class="fare-probe__src text-(--ok) border-(--ok)">AUTO</span>
                                            <div class="fare-probe__formula">
                                                ({{ $trace['base_price']['value'] }} + {{ $distanceNm }} × {{ $trace['per_nm']['value'] }}) × {{ $trace['multiplier']['value'] }}@if ($lowCost) × {{ $lowCostFactor }}@endif
                                                @if ($trace['base_price']['source'] === 'subfleet' || $trace['per_nm']['source'] === 'subfleet' || $trace['multiplier']['source'] === 'subfleet')
                                                    · {{ __('filament.fare_probe_inputs_from_subfleet') }}
                                                @endif
                                            </div>
                                        @else
                                            <span class="font-mono">{{ \Filament\Support\format_money((float) $trace['price']['value'], $currency) }}</span>
                                            <span class="fare-probe__src {{ $sourceBadge[$trace['price']['source']][1] }}">{{ $sourceBadge[$trace['price']['source']][0] }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="font-mono">{{ \Filament\Support\format_money((float) $trace['cost']['value'], $currency) }}</span>
                                        <span class="fare-probe__src {{ $sourceBadge[$trace['cost']['source']][1] }}">{{ $sourceBadge[$trace['cost']['source']][0] }}</span>
                                    </td>
                                    <td>
                                        <span class="font-mono">{{ number_format((int) $trace['capacity']['value']) }}</span>
                                        <span class="fare-probe__src {{ $sourceBadge[$trace['capacity']['source']][1] }}">{{ $sourceBadge[$trace['capacity']['source']][0] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @empty
            <div class="fare-probe__quiet">{{ __('filament.fare_probe_no_subfleets') }}</div>
        @endforelse
    </div>
@endif
