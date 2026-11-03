{{--
    Read-only PIREP slide-over (mockups/admin-hybrid/e-list.html panel). Rendered
    as the modal content of PeekAction; the theme section "PIREP slide-over
    panel" styles every .pp-* hook and hides Filament's own header/footer.
--}}
@php
    use App\Support\Units\Time;

    /** @var \App\Models\Pirep $record */
    $chipVariant = match ($record->state->getColor()) {
        'success' => 'chip--ok',
        'warning' => 'chip--warn',
        'danger'  => 'chip--bad',
        'info'    => 'chip--info',
        default   => 'chip--mute',
    };

    $mountPeek = fn (string $key): string => "replaceMountedAction('peek', {}, { table: true, recordKey: ".\Illuminate\Support\Js::from($key).' })';

    $blockTime = sprintf('%d:%02d', ...array_values(Time::minutesToTimeParts((int) ($record->flight_time ?? 0))));
    $landingRate = $record->landing_rate !== null ? (float) $record->landing_rate : null;
    $aircraft = $record->aircraft;
    $dash = '—';
@endphp

<div class="pp" data-pirep-panel>
    <header class="pp-strip" data-pirep-strip>
        <div class="pp-strip-title">
            <div class="pp-eyebrow">{{ $eyebrow }}</div>
            <h2 class="pp-heading">
                <span class="pp-ident">{{ $record->ident }}</span>
                <span class="pp-route">{{ $record->dpt_airport_id }} → {{ $record->arr_airport_id }}</span>
                <span class="chip {{ $chipVariant }}">{{ $record->state->getLabel() }}</span>
            </h2>
        </div>

        <span class="pp-position">{{ $navigation['position'] }} / {{ $navigation['total'] }}</span>
        <button type="button" class="pp-iconbtn" data-pirep-nav="previous" aria-label="Previous record" title="k"
            @disabled($navigation['previous'] === null)
            @if ($navigation['previous'] !== null) wire:click="{{ $mountPeek($navigation['previous']) }}" @endif>
            @svg('phosphor-caret-up-light')
        </button>
        <button type="button" class="pp-iconbtn" data-pirep-nav="next" aria-label="Next record" title="j"
            @disabled($navigation['next'] === null)
            @if ($navigation['next'] !== null) wire:click="{{ $mountPeek($navigation['next']) }}" @endif>
            @svg('phosphor-caret-down-light')
        </button>

        <span class="pp-sep" aria-hidden="true"></span>

        <a href="{{ $openUrl }}" class="pp-btn" data-pirep-open title="o">
            @svg('phosphor-arrow-square-out-light')
            <span class="pp-open-label">Open full page</span>
            <kbd>o</kbd>
        </a>

        @foreach ($decisions as $decision)
            {{ $decision }}
        @endforeach

        <button type="button" class="pp-iconbtn" aria-label="{{ __('filament::components/modal.actions.close.label') }}"
            x-on:click="$dispatch('close-modal', { id: $root.closest('[id]')?.id })">
            @svg('phosphor-x-light')
        </button>
    </header>

    <div class="pp-body">
        <section class="pp-flightstrip" aria-label="Route">
            <div class="pp-ap">
                <b>{{ $record->dpt_airport_id }}</b>
                <span>{{ $record->dpt_airport?->name }}</span>
                <small>{{ $record->block_off_time ? 'Off '.$record->block_off_time->format('H:i').'Z' : $dash }}</small>
            </div>
            <div class="pp-arc">
                <svg viewBox="0 0 200 56" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M2 50 Q100 -14 198 50" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
                </svg>
                <span class="pp-arc-mid">{{ $blockTime }}</span>
            </div>
            <div class="pp-ap pp-ap-end">
                <b>{{ $record->arr_airport_id }}</b>
                <span>{{ $record->arr_airport?->name }}</span>
                <small>{{ $record->block_on_time ? 'On '.$record->block_on_time->format('H:i').'Z' : $dash }}</small>
            </div>
        </section>

        <dl class="pp-stats">
            <div>
                <dt>{{ __('pireps.flight_time') }}</dt>
                <dd>{{ $blockTime }}</dd>
            </div>
            <div>
                <dt>{{ __('common.distance') }}</dt>
                <dd>@if ($record->distance){{ number_format((float) $record->distance->local()) }}<small>{{ $unitDistance }}</small>@else{{ $dash }}@endif</dd>
            </div>
            <div>
                <dt>{{ __('pireps.fuel_used') }}</dt>
                <dd>@if ($record->fuel_used){{ number_format((float) $record->fuel_used->local()) }}<small>{{ $unitFuel }}</small>@else{{ $dash }}@endif</dd>
            </div>
            <div>
                <dt>{{ __('pireps.landing_rate') }}</dt>
                <dd>@if ($landingRate){{ number_format($landingRate) }}<small>fpm</small>@else{{ $dash }}@endif</dd>
            </div>
        </dl>

        @if (filled($record->route))
            <section class="pp-section">
                <h3>{{ __('flights.route') }}</h3>
                <p class="pp-routestring">{{ $record->route }}</p>
            </section>
        @endif

        <dl class="pp-props">
            <div><dt>{{ __('common.state') }}</dt><dd><span class="chip {{ $chipVariant }}">{{ $record->state->getLabel() }}</span></dd></div>
            <div><dt>{{ trans_choice('common.pilot', 1) }}</dt><dd>{{ $record->user?->name ?? $dash }}</dd></div>
            <div><dt>{{ __('pireps.tail') }}</dt><dd>{{ $aircraft ? trim($aircraft->registration.' '.($aircraft->icao ? '('.$aircraft->icao.')' : '')) : $dash }}</dd></div>
            <div><dt>Flight type</dt><dd>{{ $record->flight_type?->getLabel() ?? $dash }}</dd></div>
            <div><dt>{{ __('pireps.source') }}</dt><dd>{{ $record->source?->getLabel() ?? $dash }}</dd></div>
            <div><dt>{{ __('pireps.block_fuel') }}</dt><dd>{{ $record->block_fuel ? number_format((float) $record->block_fuel->local()).' '.$unitFuel : $dash }}</dd></div>
            <div><dt>{{ __('pireps.planned_distance') }}</dt><dd>{{ $record->planned_distance ? number_format((float) $record->planned_distance->local()).' '.$unitDistance : $dash }}</dd></div>
        </dl>
    </div>
</div>
