{{--
    Read-only airport slide-over. Rendered as the modal content of the airport
    PeekAction; reuses the PIREP panel's .pp-* hooks and data-pirep-* keyboard
    attributes.
--}}
@php
    /** @var \App\Models\Airport $record */
    $mountPeek = fn (string $key): string => "replaceMountedAction('peek', {}, { table: true, recordKey: ".\Illuminate\Support\Js::from($key).' })';
    $dash = '—';
    $money = fn (?float $value): string => $value !== null ? number_format($value, 2) : $dash;
@endphp

<div class="pp" data-pirep-panel>
    <header class="pp-strip" data-pirep-strip>
        <div class="pp-strip-title">
            <div class="pp-eyebrow">{{ $eyebrow }}</div>
            <h2 class="pp-heading">
                <span class="pp-ident">{{ $record->icao }}</span>
                <span class="pp-route">{{ $record->name }}</span>
                @if ($record->hub)
                    <span class="chip chip--ok">{{ __('airports.hub') }}</span>
                @endif
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

        <button type="button" class="pp-iconbtn" aria-label="{{ __('filament::components/modal.actions.close.label') }}"
            x-on:click="$dispatch('close-modal', { id: $root.closest('[id]')?.id })">
            @svg('phosphor-x-light')
        </button>
    </header>

    <div class="pp-body">
        <dl class="pp-stats">
            <div>
                <dt>IATA</dt>
                <dd>{{ filled($record->iata) ? $record->iata : $dash }}</dd>
            </div>
            <div>
                <dt>{{ __('airports.latitude') }}</dt>
                <dd>{{ $record->lat ?? $dash }}</dd>
            </div>
            <div>
                <dt>{{ __('airports.longitude') }}</dt>
                <dd>{{ $record->lon ?? $dash }}</dd>
            </div>
            <div>
                <dt>{{ __('airports.elevation') }}</dt>
                <dd>@if ($record->elevation !== null){{ number_format($record->elevation) }}<small>ft</small>@else{{ $dash }}@endif</dd>
            </div>
        </dl>

        <dl class="pp-props">
            <div><dt>{{ __('common.country') }}</dt><dd>{{ filled($record->country) ? strtoupper($record->country) : $dash }}</dd></div>
            <div><dt>{{ __('user.location') }}</dt><dd>{{ filled($record->location) ? $record->location : $dash }}</dd></div>
            <div><dt>{{ __('airports.region') }}</dt><dd>{{ filled($record->region) ? $record->region : $dash }}</dd></div>
            <div><dt>{{ __('common.timezone') }}</dt><dd>{{ filled($record->timezone) ? $record->timezone : $dash }}</dd></div>
            <div><dt>{{ __('airports.ground_handling_cost') }}</dt><dd>{{ $money($record->ground_handling_cost) }}</dd></div>
            <div><dt>{{ __('airports.fuel_jeta_cost') }}</dt><dd>{{ $money($record->fuel_jeta_cost) }}</dd></div>
            <div><dt>{{ __('airports.fuel_100ll_cost') }}</dt><dd>{{ $money($record->fuel_100ll_cost) }}</dd></div>
            <div><dt>{{ __('airports.fuel_mogas_cost') }}</dt><dd>{{ $money($record->fuel_mogas_cost) }}</dd></div>
        </dl>

        @if (filled($record->notes))
            <section class="pp-section">
                <h3>{{ trans_choice('common.notes', 2) }}</h3>
                <div>{!! \Illuminate\Support\Str::sanitizeHtml($record->notes) !!}</div>
            </section>
        @endif
    </div>
</div>
