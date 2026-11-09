{{--
    PIREP record banner title row (direction E's .e-rtitle): mono flight
    ident, mono route, state chip. Returned from ViewPirep::getHeading() so
    Filament's own header still renders it; classes are styled in theme.css
    under .fi-pirep-record.
--}}
@php
    /** @var \App\Models\Pirep $record */
    $chipClass = match ($record->state->getColor()) {
        'success' => 'chip--ok',
        'warning' => 'chip--warn',
        'danger'  => 'chip--bad',
        'info'    => 'chip--info',
        default   => 'chip--mute',
    };
@endphp
<span class="rec-ident">{{ $record->ident }}</span>
<span class="rec-route">{{ $record->dpt_airport_id }} @svg('phosphor-arrow-right-light', 'rec-route__arrow') {{ $record->arr_airport_id }}</span>
<span class="chip {{ $chipClass }}">{{ $record->state->getLabel() }}</span>
