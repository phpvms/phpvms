{{--
    Quick-filter bar (mockup pireps.html:426-465, .filters/.field family).
    Renders in the table's header slot, above the toolbar. The selects bind
    directly onto Filament's tableFilters state — same source of truth as
    the filters card in the context column — and apply live (the table has
    deferFilters(false)).

    @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $records
--}}
@php
    use App\Enums\PirepState;
    use App\Models\Airline;

    $states = collect(PirepState::cases())
        ->reject(fn (PirepState $state): bool => in_array($state, [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED], true))
        ->mapWithKeys(fn (PirepState $state): array => [$state->value => $state->getLabel()]);

    $airlines = Airline::orderBy('name')->pluck('name', 'id');
@endphp

<div class="filters">
    <span class="field">
        @svg('tabler-filter')
        <label class="sr-only" for="pirep-filter-state">{{ __('common.state') }}</label>
        <select id="pirep-filter-state" wire:model.live="tableFilters.state.value">
            <option value="">{{ __('common.state') }}</option>
            @foreach ($states as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </span>

    <span class="field">
        @svg('tabler-plane')
        <label class="sr-only" for="pirep-filter-airline">{{ __('common.airline') }}</label>
        <select id="pirep-filter-airline" wire:model.live="tableFilters.airline.value">
            <option value="">{{ __('common.airline') }}</option>
            @foreach ($airlines as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </span>

    <span class="filters__spacer"></span>

    <span class="result-count">{!! __('filament.showing_of', [
        'current' => '<b>'.$records->count().'</b>',
        'total'   => '<b>'.number_format($records->total()).'</b>',
    ]) !!}</span>
</div>
