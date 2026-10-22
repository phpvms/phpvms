<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Pages\Reports\BaseReportPage;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Reads the `start_date` / `end_date` / `airlines` filter set that a page hands
 * its widgets, and supplies the period to use when the page hands over nothing.
 *
 * Three pages produce that set and none of them agree on how: the Reports pages
 * resolve it from their period picker ({@see BaseReportPage}),
 * the Dashboard exposes it as a filters form, and the Finances page builds its
 * own. A widget can also be dropped on a page that supplies no filters at all,
 * because the Dashboard lets you add any widget to it.
 *
 * That last case is what this trait exists for. An absent date filter means
 * "the default period", never "no rows" — and every widget has to agree on what
 * that period is, or a dashboard reads as a set of mutually contradictory
 * numbers. Reading the keys off `$pageFilters` by hand is also how the
 * undefined-key bugs crept in: the page passes `[]`, not `null`, when it has no
 * filters, so `$this->pageFilters ?? [...]` keeps the empty array and the key
 * reads blow up.
 */
trait ReadsPageFilters
{
    use InteractsWithPageFilters;

    /**
     * The period a widget covers when its page supplies no date filter. Wide
     * enough that an ordinarily quiet week does not render as a row of zeros.
     */
    protected const int DEFAULT_FILTER_DAYS = 30;

    /**
     * A filter set announced by the page after the initial render, held apart
     * from `$pageFilters` because that one is `#[Reactive]` and Livewire throws
     * `CannotMutateReactivePropException` if a child writes to it.
     *
     * @var array<string, mixed>|null
     */
    public ?array $announcedFilters = null;

    /**
     * Take a new filter set announced by the page.
     *
     * The dashboard's widget grid sits inside a `wire:ignore` element, because
     * GridStack owns that DOM. A parent re-render is discarded there, so an
     * updated `#[Reactive]` prop never reaches a widget on a Livewire round
     * trip. Events do reach it, since they are delivered per component rather
     * than through the parent's morph.
     *
     * @param array<string, mixed> $filters
     */
    #[On('dashboard-period-changed')]
    public function applyDashboardPeriod(array $filters): void
    {
        $this->announcedFilters = $filters;
    }

    /**
     * The filter set in effect: whatever the page last announced, else the
     * props the widget was rendered with.
     *
     * @return array<string, mixed>
     */
    private function activeFilters(): array
    {
        return $this->announcedFilters ?? $this->pageFilters ?? [];
    }

    /**
     * Inclusive start of the filtered period, at midnight.
     */
    protected function filterStartDate(): Carbon
    {
        $start = $this->activeFilters()['start_date'] ?? null;

        return filled($start)
            ? Carbon::parse($start)->startOfDay()
            : now()->subDays(static::DEFAULT_FILTER_DAYS - 1)->startOfDay();
    }

    /**
     * Inclusive end of the filtered period, at the last moment of the day, so a
     * report filed this afternoon still falls inside a range ending today.
     */
    protected function filterEndDate(): Carbon
    {
        $end = $this->activeFilters()['end_date'] ?? null;

        return filled($end) ? Carbon::parse($end)->endOfDay() : now()->endOfDay();
    }

    /**
     * Selected airline ids. Empty means every airline.
     *
     * @return array<int, string>
     */
    protected function filterAirlines(): array
    {
        $airlines = $this->activeFilters()['airlines'] ?? [];

        return is_array($airlines) ? $airlines : [];
    }
}
