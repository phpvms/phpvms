@php
    use App\Enums\PirepState;

    /** @var \Filament\Resources\Pages\ListRecords $this */
    $records = $this->getTableRecords();
    $currentState = data_get($this->tableFilters, 'state.value');

    // State pills replace the old tabs. Each one drives the `state` SelectFilter.
    $statePills = [
        ['key' => null,                          'label' => __('filament-tables::table.filters.multi_select.placeholder'), 'color' => 'gray'],
        ['key' => PirepState::PENDING->value,    'label' => PirepState::PENDING->getLabel(),  'color' => 'warning'],
        ['key' => PirepState::ACCEPTED->value,   'label' => PirepState::ACCEPTED->getLabel(), 'color' => 'success'],
        ['key' => PirepState::REJECTED->value,   'label' => PirepState::REJECTED->getLabel(), 'color' => 'danger'],
    ];

    // "More filters" modal — replicates the Filament filters dialog wired
    // to FiltersLayout::Modal. We render the trigger + modal manually because
    // our custom page doesn't include EmbeddedTable (which normally hosts it).
    $table = $this->getTable();
    $filtersTriggerAction = $table->getFiltersTriggerAction();
    $activeFiltersCount = $table->getActiveFiltersCount();
    $filtersForm = $this->getTableFiltersForm();
    $filtersApplyAction = $table->getFiltersApplyAction();
@endphp

<x-filament-panels::page>
    <div class="fi-pirep-list">
        {{-- Toolbar: search + state pills + filters trigger --}}
        <div class="fi-pirep-toolbar">
            <div class="fi-pirep-toolbar-search">
                <x-filament-tables::search-field
                    :placeholder="__('filament-tables::table.fields.search.placeholder')"
                />
            </div>

            <div class="fi-pirep-toolbar-pills" role="tablist" aria-label="{{ __('common.state') }}">
                @foreach ($statePills as $pill)
                    @php
                        $isActive = (string) ($currentState ?? '') === (string) ($pill['key'] ?? '');
                    @endphp
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $isActive ? 'true' : 'false' }}"
                        wire:click="$set('tableFilters.state.value', @js($pill['key']))"
                        @class([
                            'fi-pirep-pill',
                            'fi-pirep-pill-active' => $isActive,
                            'fi-pirep-pill-' . $pill['color'],
                        ])
                    >
                        {{ $pill['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="fi-pirep-toolbar-filters">
                <x-filament::modal
                    :heading="__('filament-tables::table.filters.heading')"
                    :wire:key="$this->getId() . '.table.filters'"
                    :footer-actions="[$filtersApplyAction->close()]"
                    class="fi-ta-filters-modal"
                    width="lg"
                >
                    <x-slot name="trigger">
                        {{ $filtersTriggerAction->badge($activeFiltersCount) }}
                    </x-slot>

                    {{ $filtersForm }}
                </x-filament::modal>
            </div>
        </div>

        {{-- Card list --}}
        @if (! $records || (method_exists($records, 'isEmpty') && $records->isEmpty()) || (! method_exists($records, 'isEmpty') && count($records) === 0))
            <div class="fi-pirep-empty">
                <p class="fi-pirep-empty-heading">
                    {{ __('filament-tables::table.empty.heading.default', ['model' => trans_choice('common.pirep', 2)]) }}
                </p>
            </div>
        @else
            <div class="fi-pirep-card-list">
                @foreach ($records as $record)
                    @php
                        $recordKey = $this->getTableRecordKey($record);
                    @endphp
                    <article
                        wire:key="pirep-card-{{ $recordKey }}"
                        class="fi-pirep-card"
                    >
                        @include('filament.pireps.row', ['record' => $record, 'recordKey' => $recordKey])
                    </article>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if ($records instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div class="fi-pirep-pagination">
                    <x-filament::pagination :paginator="$records" />
                </div>
            @endif
        @endif
    </div>

    {{-- Render action modals (view/accept/reject/etc).
         The default page layout skips this for HasTable components because
         the table container normally renders modals itself. Since this page
         replaces the table container with custom cards, we render them here. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
