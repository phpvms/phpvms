{{--
    PIREP record banner extras (direction E's .e-rbanner), returned from
    ViewPirep::getSubheading(): the "Back to list" button, the filed-by line
    and the dashed route arc. The button and arc are positioned against the
    banner by theme.css (.fi-pirep-record); the arc is hidden below 1100px.
    The data-pirep-back hook is what pirep-record-back.js binds Esc to.
--}}
@php
    use App\Support\Units\Time;

    /** @var \App\Models\Pirep $record */
    $block = $record->flight_time
        ? sprintf('%d:%02d', ...array_values(Time::minutesToTimeParts((int) $record->flight_time)))
        : null;
    $middle = implode(' · ', array_filter([$block, $record->aircraft?->icao], filled(...)));
@endphp
<a class="rec-back" href="{{ $backUrl }}" data-pirep-back>
    @svg('phosphor-arrow-left-light') {{ __('pireps.back_to_list') }}
</a>

@if (filled($filed))
    <span class="rec-filed">{{ $filed }}</span>
@endif

<span class="rec-arc" aria-hidden="true">
    <svg viewBox="0 0 400 54" preserveAspectRatio="none">
        <path d="M6 50 Q200 -30 394 50" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="1.5 6" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        <circle cx="6" cy="50" r="4" fill="none" stroke="#fff" stroke-width="1.5" vector-effect="non-scaling-stroke" />
        <circle cx="394" cy="50" r="4" fill="#fff" stroke="#fff" stroke-width="1.5" vector-effect="non-scaling-stroke" />
    </svg>
    <span class="rec-arc__ap rec-arc__ap--l">{{ $record->dpt_airport_id }}@if ($record->dpt_airport?->name)<small>{{ $record->dpt_airport->name }}</small>@endif</span>
    @if ($middle !== '')
        <span class="rec-arc__mid">{{ $middle }}</span>
    @endif
    <span class="rec-arc__ap rec-arc__ap--r">{{ $record->arr_airport_id }}@if ($record->arr_airport?->name)<small>{{ $record->arr_airport->name }}</small>@endif</span>
</span>
