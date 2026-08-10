{{--
    Inline metrics row rendered as the page subheading (band header "eyebrow"
    variant), mirroring filament/pireps/partials/head-metrics.blade.php.
    Structure/classes are load-bearing for theme.css.
--}}
<span class="head-metrics">
    <span class="m-mute"><b>{{ $listed }}</b><em>{{ __('addon-manager::addons.metric_listed') }}</em></span>
    <span class="m-warn"><b>{{ $updates }}</b><em>{{ trans_choice('addon-manager::addons.metric_updates', $updates) }}</em></span>
    <span class="m-mute"><b>{{ $disabled }}</b><em>{{ __('addon-manager::addons.metric_disabled') }}</em></span>
</span>
