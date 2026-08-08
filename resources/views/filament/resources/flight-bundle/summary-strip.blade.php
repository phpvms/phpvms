{{--
    Bundle summary cards — same .strip pattern as the pirep detail header
    (resources/views/filament/pireps/partials/detail/header.blade.php) and the
    dashboard stats strip. Deliberately page-local until the design is nailed
    down; then it can become a shared component.

    Rendered by EditFlightBundle::content() above the schedules table. The
    last cell carries the Edit-details trigger; the slideover it opens is the
    only place the bundle's own fields are edited.
--}}
@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    /** @var \App\Models\FlightBundle $record */
    $flightsTotal = $record->flights()->count();
    $flightsEnabled = $record->flights()->where('enabled', true)->count();
    $subfleetCount = $record->subfleets()->count();
    $tailCount = \App\Models\Aircraft::query()
        ->whereIn('subfleet_id', $record->subfleets()->select('subfleets.id'))
        ->count();

    $windowDate = fn (?Carbon $date): string => $date === null
        ? '—'
        : ($date->year === now()->year ? $date->format('j M') : $date->format('j M Y'));
    $window = ($record->start_date === null && $record->end_date === null)
        ? __('filament.bundles.window_always')
        : $windowDate($record->start_date) . ' → ' . $windowDate($record->end_date);
@endphp

<section class="strip strip--4" aria-label="{{ __('filament.bundles.sections.details') }}">
    <div class="strip__cell">
        <span class="strip__icon">@svg('tabler-power')</span>
        <span class="strip__label">{{ __('common.status') }}</span>
        <span class="strip__value">{{ $record->enabled ? __('common.enabled') : __('common.disabled') }}</span>
        <span class="strip__note">{{ $record->visible ? 'Visible to pilots' : 'Hidden from pilots' }}</span>
    </div>

    <div class="strip__cell">
        <span class="strip__icon strip__icon--blue">@svg('tabler-plane')</span>
        <span class="strip__label">{{ trans_choice('common.flight', 2) }}</span>
        <span class="strip__value">{{ number_format($flightsTotal) }}</span>
        <span class="strip__note">{{ $flightsEnabled }} enabled · {{ $flightsTotal - $flightsEnabled }} disabled</span>
    </div>

    <div class="strip__cell">
        <span class="strip__icon strip__icon--teal">@svg('tabler-stack-2')</span>
        <span class="strip__label">{{ trans_choice('common.subfleet', 2) }}</span>
        <span class="strip__value">{{ number_format($subfleetCount) }}</span>
        <span class="strip__note">{{ $tailCount }} {{ Str::plural('tail', $tailCount) }}</span>
    </div>

    <div class="strip__cell strip__cell--edit">
        <span class="strip__icon strip__icon--violet">@svg('tabler-calendar-event')</span>
        <span class="strip__label">{{ __('filament.bundles.window') }}</span>
        <span class="strip__value">{{ $window }}</span>
        <span class="strip__note">{{ filled($record->description) ? Str::limit($record->description, 60) : __('filament.bundles.no_description') }}</span>
        <div class="strip__edit">{{ $this->editAction }}</div>
    </div>
</section>
