{{--
    Dashboard header: Filament's own <x-filament-panels::header> markup (so the
    console hero styling in theme.css keeps applying) with the period picker
    dropped into the actions slot ahead of the layout actions. Mirrors
    filament/reports/partials/header.blade.php, minus the airline picker — the
    dashboard covers the whole operation.
--}}
@php
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

        @if (filled($subheading = $this->getSubheading()))
            <p class="fi-header-subheading">
                {{ $subheading }}
            </p>
        @endif
    </div>

    <div class="fi-header-actions-ctn fi-report-filters">
        {{-- Period picker: quick ranges plus an absolute range, in one panel. --}}
        <x-filament::dropdown placement="bottom-end" width="xs" teleport>
            <x-slot name="trigger">
                <button
                    type="button"
                    class="fi-report-filter-trigger"
                    data-dashboard-period-trigger
                >
                    <x-filament::icon
                        icon="phosphor-calendar-dots-light"
                        class="fi-report-filter-trigger-icon"
                    />
                    <span>{{ $this->getPeriodLabel() }}</span>
                    <x-filament::icon
                        :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::CaretDownLight"
                        class="fi-report-filter-trigger-caret"
                    />
                </button>
            </x-slot>

            <div class="fi-report-picker">
                <p class="fi-report-picker-label">
                    {{ __('filament.reports_quick_ranges') }}
                </p>

                <ul class="fi-report-picker-quick">
                    @foreach (\App\Filament\Pages\Dashboard::PERIODS as $quickRange)
                        <li>
                            <button
                                type="button"
                                aria-pressed="{{ $this->period === $quickRange ? 'true' : 'false' }}"
                                wire:click="setPeriod('{{ $quickRange }}')"
                            >
                                {{ \App\Filament\Pages\Dashboard::getQuickRangeLabel($quickRange) }}
                            </button>
                        </li>
                    @endforeach
                </ul>

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
            </div>
        </x-filament::dropdown>

        <x-filament::actions :actions="$headerActions" />
    </div>
</header>
