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

    /* Addon-registered subtabs, already resolved and rendered to HTML by
       ViewPirep::getViewData() — see the docblock there for why the panels
       cannot be rendered from inside this view.
       @var array<int, array{id: string, domId: string, label: string, badge: string|int|null, html: string}> $extensionTabs */
    $extensionTabs = $extensionTabs ?? [];
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
                @svg('phosphor-airplane-light') Flight
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
                @svg('phosphor-list-light') {{ __('pireps.flight_log') }} <em>{{ $logEntries->count() }}</em>
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
                @svg('phosphor-money-light') {{ __('pireps.finance') }}
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
                @svg('phosphor-archive-light') {{ __('filament.original_flight') }}
            </button>

            {{-- Addon-registered subtabs, after the built-ins --}}
            @foreach ($extensionTabs as $tab)
                <button
                    type="button"
                    class="subtab"
                    role="tab"
                    :aria-selected="activeTab === @js($tab['id'])"
                    aria-controls="tab-{{ $tab['domId'] }}"
                    id="t-{{ $tab['domId'] }}"
                    @click="activeTab = @js($tab['id'])"
                >
                    {{ $tab['label'] }}@if (filled($tab['badge'])) <em>{{ $tab['badge'] }}</em>@endif
                </button>
            @endforeach
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

        {{-- Addon-registered panels, pre-rendered (and error-contained) by the
             page class. --}}
        @foreach ($extensionTabs as $tab)
            <div id="tab-{{ $tab['domId'] }}" role="tabpanel" aria-labelledby="t-{{ $tab['domId'] }}" x-show="activeTab === @js($tab['id'])" x-cloak>
                {!! $tab['html'] !!}
            </div>
        @endforeach
    </section>

    @include('filament.pireps.partials.detail.sidebar', ['record' => $record])
</div>
