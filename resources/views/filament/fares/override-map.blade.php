{{--
    Override map for one fare: base card on the left, then the two override
    layers in application order — subfleet pivots, then flight pivots, which
    stack on top of whichever subfleet's values apply at pricing time.
    Rendered inside the "Overrides" row-action modal on the Fares list.

    Expects: $fare (Fare with subfleets + flights pivot-loaded).
--}}
@php
    use App\Filament\Resources\Fares\Support\FareTrace;

    $currency = setting('units.currency');

    $overridingSubfleets = $fare->subfleets->filter(fn ($subfleet) => FareTrace::pivotOverrides($subfleet->pivot));
    $plainSubfleets = $fare->subfleets->count() - $overridingSubfleets->count();

    $overridingFlights = $fare->flights->filter(fn ($flight) => FareTrace::pivotOverrides($flight->pivot));
    $plainFlights = $fare->flights->count() - $overridingFlights->count();

    $chipLabels = [
        'price'      => __('common.price'),
        'cost'       => __('common.cost'),
        'capacity'   => __('common.capacity'),
        'base_price' => 'base price',
        'per_nm'     => '/nm',
        'multiplier' => '×',
    ];
@endphp

<div class="fare-map">
    {{-- Layer 1: the base fare --}}
    <div class="fare-map__base">
        <div class="fare-map__eyebrow">{{ __('filament.fare_map_base') }}</div>
        <div class="fare-map__title">
            <span class="font-mono font-semibold">{{ $fare->code }}</span>
            <span class="text-(--ink-2)">{{ $fare->name }}</span>
        </div>
        <dl class="fare-map__facts">
            <div><dt>{{ __('common.price') }}</dt><dd>{{ \Filament\Support\format_money((float) $fare->price, $currency) }}</dd></div>
            <div><dt>{{ __('common.cost') }}</dt><dd>{{ \Filament\Support\format_money((float) $fare->cost, $currency) }}</dd></div>
            <div><dt>{{ __('common.capacity') }}</dt><dd>{{ $fare->capacity }}</dd></div>
            @if (setting('fares.auto_price'))
                <div><dt>base price</dt><dd>{{ \Filament\Support\format_money((float) $fare->base_price, $currency) }}</dd></div>
                <div><dt>/nm</dt><dd>{{ $fare->per_nm }}</dd></div>
                <div><dt>×</dt><dd>{{ $fare->multiplier }}</dd></div>
            @endif
        </dl>
    </div>

    {{-- Layer 2: subfleet overrides --}}
    <div class="fare-map__col">
        <div class="fare-map__eyebrow">{{ trans_choice('common.subfleet', 2) }}</div>

        @forelse ($overridingSubfleets as $subfleet)
            @php $trace = FareTrace::resolve($fare, $subfleet->pivot); @endphp
            <div class="fare-map__node">
                <div class="fare-map__node-title">
                    <span class="font-semibold">{{ $subfleet->name }}</span>
                    <span class="font-mono text-[11px] text-(--ink-4)">{{ $subfleet->type }}</span>
                </div>
                <div class="fare-map__chips">
                    @foreach ([...FareTrace::FIELDS, ...FareTrace::AUTO_FIELDS] as $field)
                        @if ($trace[$field]['subfleet'] !== null)
                            <span class="fare-map__chip">
                                {{ $chipLabels[$field] }}
                                <b>{{ $trace[$field]['subfleet']['raw'] }}</b>
                                @if (str_ends_with($trace[$field]['subfleet']['raw'], '%'))
                                    <i>→ {{ $trace[$field]['subfleet']['value'] }}</i>
                                @endif
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <div class="fare-map__quiet">{{ __('filament.fare_map_no_subfleet_overrides') }}</div>
        @endforelse

        @if ($plainSubfleets > 0)
            <div class="fare-map__quiet">+ {{ $plainSubfleets }} {{ __('filament.fare_map_attach_unchanged') }}</div>
        @endif
    </div>

    {{-- Layer 3: flight overrides, applied after the subfleet's values --}}
    <div class="fare-map__col">
        <div class="fare-map__eyebrow">
            {{ trans_choice('common.flight', 2) }}
            <span class="fare-map__hint">{{ __('filament.fare_map_flights_hint') }}</span>
        </div>

        @forelse ($overridingFlights as $flight)
            <div class="fare-map__node">
                <div class="fare-map__node-title">
                    <span class="font-mono font-semibold">{{ $flight->ident }}</span>
                    <span class="font-mono text-[11px] text-(--ink-4)">{{ $flight->dpt_airport_id }} → {{ $flight->arr_airport_id }}</span>
                </div>
                <div class="fare-map__chips">
                    @foreach (FareTrace::FIELDS as $field)
                        @if (filled($flight->pivot->{$field}))
                            <span class="fare-map__chip">{{ $chipLabels[$field] }} <b>{{ $flight->pivot->{$field} }}</b></span>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <div class="fare-map__quiet">{{ __('filament.fare_map_no_flight_overrides') }}</div>
        @endforelse

        @if ($plainFlights > 0)
            <div class="fare-map__quiet">+ {{ $plainFlights }} {{ __('filament.fare_map_attach_unchanged') }}</div>
        @endif
    </div>
</div>
