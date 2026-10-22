<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\HasPeriodFilter;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Override;
use UnitEnum;

/**
 * Shared behaviour for the Reports pages (Flights, Pilots, Aircraft): the
 * Reports navigation group, the period + airline filters that sit in the page
 * header, and the page skeleton.
 *
 * Filter state lives in the query string so a report can be linked or
 * bookmarked, and is mirrored into one session key shared by every report page
 * so the selection carries over when moving between them.
 */
abstract class BaseReportPage extends Page
{
    use HasPeriodFilter;

    /**
     * One session key for the whole Reports hub — not per class — so the
     * period/airline selection carries over between Flights, Pilots and
     * Aircraft.
     */
    private const string SESSION_KEY = 'reports_filters';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    /**
     * Resolved filter set handed to every widget as `$pageFilters` by
     * {@see Page::getWidgetsSchemaComponents()}, which reads this property
     * directly. Recomputed from the URL state on each request.
     *
     * @var array<string, mixed>|null
     */
    public ?array $filters = null;

    public function mount(): void
    {
        $this->restorePeriodFilterState(session(self::SESSION_KEY));
    }

    public function booted(): void
    {
        $this->refreshFilters();
    }

    /**
     * Livewire applies property updates after `booted()`, so the resolved
     * filter set has to be rebuilt once more before the widgets render.
     */
    public function updated(): void
    {
        $this->refreshFilters();
    }

    #[Override]
    public function getHeader(): ?View
    {
        return view('filament.reports.partials.header', [
            'airlineOptions' => $this->getAirlineOptions(),
        ]);
    }

    /**
     * @return array<class-string<Widget>>
     */
    abstract public function getWidgets(): array;

    public function getWidgetsContentComponent(): Component
    {
        // 2 columns, like the dashboard: span-1 stat charts sit side-by-side
        // and span-full widgets (history tables, hbar charts) take a whole row.
        return Grid::make(2)
            ->columnSpanFull()
            ->schema($this->getWidgetsSchemaComponents($this->getWidgets()));
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getWidgetsContentComponent(),
            ]);
    }

    /**
     * Resolve the selection into the shape the widgets consume, and remember it
     * for the other report pages.
     */
    protected function refreshFilters(): void
    {
        $this->filters = $this->resolvedPeriodFilters();

        session()->put(self::SESSION_KEY, $this->periodFilterState());
    }
}
