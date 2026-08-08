{{--
    Inline metrics row rendered as the PIREP list page subheading (band
    header "eyebrow" variant), replacing the StatsOverviewWidget cards.
    Structure/classes are load-bearing for theme.css.
--}}
<span class="head-metrics">
    <span class="m-mute"><b>{{ $total }}</b><em>{{ trans_choice('common.pirep', 2) }}</em></span>
    <span class="m-warn"><b>{{ $pending }}</b><em>{{ __('pireps.state.pending') }}</em></span>
    <span class="m-info"><b>{{ $accepted }}</b><em>{{ __('pireps.state.accepted') }}</em></span>
</span>
