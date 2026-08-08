@php
    /** @var \App\Filament\Resources\Pireps\Pages\ViewPirep $this */
    /** @var \App\Models\Pirep $record */
    $record = $record ?? (isset($getRecord) ? $getRecord() : null);

    /** @var array<string, mixed> $mapFeatures */
    $mapFeatures = $mapFeatures ?? [];

    /** @var array<string, mixed>|null $performance */
    $performance = $performance ?? null;

    // Computed once here so the "Flight log <em>N</em>" subtab badge and the
    // Flight Log tab body share a single query instead of two.
    $logEntries = $this->logEntries;
@endphp

@include('filament.pireps.partials.detail.header', ['record' => $record])

<div class="with-context">
    <section class="panel" x-data="{ activeTab: 'flight' }">
        <div class="subtabs" role="tablist" aria-label="Report sections">
            <button
                type="button"
                class="subtab"
                role="tab"
                :aria-selected="activeTab === 'flight'"
                aria-controls="tab-flight"
                id="t-flight"
                @click="activeTab = 'flight'"
            >
                @svg('tabler-plane') Flight
            </button>
            <button
                type="button"
                class="subtab"
                role="tab"
                :aria-selected="activeTab === 'log'"
                aria-controls="tab-log"
                id="t-log"
                @click="activeTab = 'log'"
            >
                @svg('tabler-list') {{ __('pireps.flight_log') }} <em>{{ $logEntries->count() }}</em>
            </button>
            <button
                type="button"
                class="subtab"
                role="tab"
                :aria-selected="activeTab === 'finances'"
                aria-controls="tab-fin"
                id="t-fin"
                @click="activeTab = 'finances'"
            >
                @svg('tabler-cash') {{ __('pireps.finance') }}
            </button>
            <button
                type="button"
                class="subtab"
                role="tab"
                :aria-selected="activeTab === 'archive'"
                aria-controls="tab-arc"
                id="t-arc"
                @click="activeTab = 'archive'"
            >
                @svg('tabler-archive') {{ __('filament.original_flight') }}
            </button>
        </div>

        {{-- Flight tab: route bar + map + vertical profile chart + touchdown + notes --}}
        <div id="tab-flight" role="tabpanel" aria-labelledby="t-flight" x-show="activeTab === 'flight'" x-cloak>
            @include('filament.pireps.partials.detail.route-performance', [
                'record'      => $record,
                'mapFeatures' => $mapFeatures,
                'performance' => $performance,
            ])
        </div>

        {{-- Flight log tab --}}
        <div id="tab-log" role="tabpanel" aria-labelledby="t-log" x-show="activeTab === 'log'" x-cloak>
            @include('filament.pireps.partials.detail.flight-log', [
                'record'     => $record,
                'logEntries' => $logEntries,
            ])
        </div>

        {{-- Finances tab --}}
        <div id="tab-fin" role="tabpanel" aria-labelledby="t-fin" x-show="activeTab === 'finances'" x-cloak>
            @include('filament.pireps.partials.detail.finances', ['record' => $record])
        </div>

        {{-- Original flight tab --}}
        <div id="tab-arc" role="tabpanel" aria-labelledby="t-arc" x-show="activeTab === 'archive'" x-cloak>
            @include('filament.pireps.partials.detail.archive', ['record' => $record])
        </div>
    </section>

    @include('filament.pireps.partials.detail.sidebar', ['record' => $record])
</div>
