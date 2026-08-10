{{--
    Two-part hero subheading: descriptive text on the left, state chip and
    figures pinned to the band's bottom-right, level with the actions slot.

    Rendered from a page's getSubheading():

        return view('filament.shared.hero-subheading', [
            'meta'    => 'Alamo Pacific Airways · Passenger (Scheduled)',
            'chip'    => ['label' => 'Enabled', 'color' => 'success'],  // optional
            'figures' => [
                ['value' => '01:50', 'label' => __('flights.flight_time')],
                ['value' => '690',   'label' => 'nm'],
            ],
        ]);

    The split is done by .fi-header-subheading's `justify-between`; emitting
    two children is what activates it. A page that returns a plain string
    subheading is unaffected — one child has nothing to space against. The
    `:has(.hero-sub__figures)` rule in the theme is what releases vendor's
    672px prose cap so the right-hand group actually reaches the edge.
--}}
@php
    $chip ??= null;
    $figures ??= [];
@endphp

<span class="hero-sub__meta">{{ $meta }}</span>

@if ($chip || $figures)
    <span class="hero-sub__figures">
        @if ($chip)
            <span @class([
                'chip',
                'chip--ok'   => ($chip['color'] ?? null) === 'success',
                'chip--bad'  => ($chip['color'] ?? null) === 'danger',
                'chip--warn' => ($chip['color'] ?? null) === 'warning',
            ])>{{ $chip['label'] }}</span>
        @endif

        @foreach ($figures as $figure)
            <span><b>{{ $figure['value'] }}</b><em>{{ $figure['label'] }}</em></span>
        @endforeach
    </span>
@endif
