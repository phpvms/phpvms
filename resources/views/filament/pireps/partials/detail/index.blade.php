@php
    /** @var \App\Filament\Resources\Pireps\Pages\ViewPirep $this */
    /** @var \App\Models\Pirep $record */
    $record = $record ?? (isset($getRecord) ? $getRecord() : null);

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

    $bannerTabs = [
        ['key' => 'flight', 'label' => 'Flight', 'icon' => 'phosphor-airplane-light', 'panel' => 'tab-flight'],
        ['key' => 'log', 'label' => __('pireps.flight_log'), 'icon' => 'phosphor-list-light', 'badge' => $logEntries->count(), 'panel' => 'tab-log'],
        ['key' => 'finances', 'label' => __('pireps.finance'), 'icon' => 'phosphor-money-light', 'panel' => 'tab-fin'],
        ['key' => 'archive', 'label' => __('filament.original_flight'), 'icon' => 'phosphor-archive-light', 'panel' => 'tab-arc'],
        ...collect($extensionTabs)->map(fn (array $tab): array => [
            'key'   => $tab['id'],
            'label' => $tab['label'],
            'badge' => $tab['badge'],
            'panel' => 'tab-'.$tab['domId'],
        ])->all(),
    ];
@endphp

{{-- Folder tabs ride the banner's bottom edge (<x-admin.banner-tabs> pulls the
     strip up over the header's reserved padding); the sheet below is the main
     pane plus the properties rail. One Alpine scope covers strip and panels. --}}
<div
    class="rec"
    x-data="{
        activeTab: 'flight',
        order: @js(['flight', 'log', 'finances', 'archive', ...collect($extensionTabs)->pluck('id')->all()]),
        pick(event) {
            if (event.metaKey || event.ctrlKey || event.altKey || event.target.closest('input, textarea, select, [contenteditable], [role=dialog]')) {
                return;
            }
            const tab = this.order[Number(event.key) - 1];
            if (tab) {
                this.activeTab = tab;
            }
        },
    }"
    @keydown.window="pick($event)"
>
    <x-admin.banner-tabs model="activeTab" aria-label="Report sections" :tabs="$bannerTabs" />

    <div class="rec-sheet">
        <div class="rec-main">
            {{-- Flight tab: figures strip + route + map + vertical profile + touchdown + notes --}}
            <div id="tab-flight" role="tabpanel" aria-labelledby="t-flight" x-show="activeTab === 'flight'" x-cloak>
                @include('filament.pireps.partials.detail.header', ['record' => $record])
                @include('filament.pireps.partials.detail.route-performance', [
                    'record'      => $record,
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
            <div id="tab-fin" role="tabpanel" aria-labelledby="t-finances" x-show="activeTab === 'finances'" x-cloak>
                @include('filament.pireps.partials.detail.finances', ['record' => $record])
            </div>

            {{-- Original flight tab --}}
            <div id="tab-arc" role="tabpanel" aria-labelledby="t-archive" x-show="activeTab === 'archive'" x-cloak>
                @include('filament.pireps.partials.detail.archive', ['record' => $record])
            </div>

            {{-- Addon-registered panels, pre-rendered (and error-contained) by the
                 page class. --}}
            @foreach ($extensionTabs as $tab)
                <div id="tab-{{ $tab['domId'] }}" role="tabpanel" aria-labelledby="t-{{ $tab['id'] }}" x-show="activeTab === @js($tab['id'])" x-cloak>
                    {!! $tab['html'] !!}
                </div>
            @endforeach
        </div>

        @include('filament.pireps.partials.detail.sidebar', ['record' => $record])
    </div>
</div>
