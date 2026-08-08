{{--
    Report page header: Filament's own <x-filament-panels::header> markup (so the
    console hero styling in theme.css keeps applying) with the period and airline
    pickers dropped into the actions slot instead of Action buttons.
--}}
@php
    [$rangeStart, $rangeEnd] = $this->resolveRange();
    $breadcrumbs = filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [];
@endphp

<header @class(['fi-header', 'fi-header-has-breadcrumbs' => $breadcrumbs])>
    <div>
        @if ($breadcrumbs)
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        @endif

        <h1 class="fi-header-heading">
            {{ $this->getHeading() }}
        </h1>

        <p class="fi-header-subheading">
            {{ $rangeStart->format('j M Y') }} – {{ $rangeEnd->format('j M Y') }}
            · {{ $this->getAirlinesLabel() }}
        </p>
    </div>

    <div class="fi-header-actions-ctn fi-report-filters">
        {{-- Period picker: quick ranges plus an absolute range, in one panel. --}}
        <x-filament::dropdown placement="bottom-end" width="xs" teleport>
            <x-slot name="trigger">
                <button type="button" class="fi-report-filter-trigger">
                    <x-filament::icon
                        icon="tabler-calendar-time"
                        class="fi-report-filter-trigger-icon"
                    />
                    <span>{{ $this->getPeriodLabel() }}</span>
                    <x-filament::icon
                        icon="heroicon-m-chevron-down"
                        class="fi-report-filter-trigger-caret"
                    />
                </button>
            </x-slot>

            <div class="fi-report-picker">
                <p class="fi-report-picker-label">
                    {{ __('filament.reports_absolute_range') }}
                </p>

                <div class="fi-report-picker-range">
                    <input
                        type="date"
                        aria-label="{{ __('common.start_date') }}"
                        max="{{ now()->toDateString() }}"
                        wire:model="start"
                    />
                    <input
                        type="date"
                        aria-label="{{ __('common.end_date') }}"
                        max="{{ now()->toDateString() }}"
                        wire:model="end"
                    />
                </div>

                <x-filament::button
                    size="sm"
                    class="fi-report-picker-apply"
                    wire:click="applyCustomRange"
                >
                    {{ __('filament.reports_apply_range') }}
                </x-filament::button>

                <p class="fi-report-picker-label">
                    {{ __('filament.reports_quick_ranges') }}
                </p>

                <ul class="fi-report-picker-quick">
                    @foreach (\App\Filament\Pages\Reports\BaseReportPage::PERIODS as $quickRange)
                        <li>
                            <button
                                type="button"
                                aria-pressed="{{ $this->period === $quickRange ? 'true' : 'false' }}"
                                wire:click="setPeriod('{{ $quickRange }}')"
                            >
                                {{ \App\Filament\Pages\Reports\BaseReportPage::getQuickRangeLabel($quickRange) }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-filament::dropdown>

        {{-- Airlines: multi-select, nothing ticked means every airline. --}}
        <x-filament::dropdown placement="bottom-end" width="xs" teleport>
            <x-slot name="trigger">
                <button type="button" class="fi-report-filter-trigger">
                    <x-filament::icon
                        icon="tabler-plane"
                        class="fi-report-filter-trigger-icon"
                    />
                    <span>{{ $this->getAirlinesLabel() }}</span>
                    <x-filament::icon
                        icon="heroicon-m-chevron-down"
                        class="fi-report-filter-trigger-caret"
                    />
                </button>
            </x-slot>

            <div class="fi-report-picker">
                <ul class="fi-report-picker-airlines">
                    <li>
                        <label>
                            <input
                                type="checkbox"
                                @checked(empty($this->airlines))
                                wire:click="clearAirlines"
                            />
                            <span>{{ __('filament.reports_all_airlines') }}</span>
                        </label>
                    </li>

                    @foreach ($airlineOptions as $airlineId => $airlineName)
                        <li>
                            <label>
                                <input
                                    type="checkbox"
                                    value="{{ $airlineId }}"
                                    wire:model.live="airlines"
                                />
                                <span>{{ $airlineName }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-filament::dropdown>
    </div>
</header>
