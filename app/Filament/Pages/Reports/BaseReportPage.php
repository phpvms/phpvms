<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\NavigationGroup;
use App\Models\Airline;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
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
    /**
     * Quick ranges are stored relative rather than as resolved dates, so a
     * bookmarked report keeps meaning "the last 30 days" instead of freezing
     * to whatever dates were current when it was saved.
     */
    public const PERIOD_CUSTOM = 'custom';

    /** @var array<int, string> */
    public const PERIODS = ['7d', '14d', '30d', 'ytd'];

    /**
     * One session key for the whole Reports hub — not per class — so the
     * period/airline selection carries over between Flights, Pilots and
     * Aircraft.
     */
    private const SESSION_KEY = 'reports_filters';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    #[Url]
    public string $period = '30d';

    /** Only read while {@see $period} is `custom`. */
    #[Url]
    public ?string $start = null;

    /** Only read while {@see $period} is `custom`. */
    #[Url]
    public ?string $end = null;

    /**
     * Selected airline ids. Empty means every airline.
     *
     * @var array<int, string>
     */
    #[Url]
    public array $airlines = [];

    /**
     * Resolved filter set handed to every widget as `$pageFilters` by
     * {@see Page::getWidgetsSchemaComponents()}, which reads this property
     * directly. Recomputed from the URL state on each request.
     *
     * @var array<string, mixed>|null
     */
    public ?array $filters = null;

    /**
     * `Airline::selectList()` is an uncached query and the header asks for it
     * from both pickers plus the subheading, so hold it for the request.
     *
     * @var array<int|string, string>|null
     */
    private ?array $airlineOptions = null;

    public function mount(): void
    {
        $stored = session(self::SESSION_KEY);

        // Anything in the query string is an explicit choice and wins over
        // whatever the previous report page left behind.
        if (!is_array($stored) || request()->hasAny(['period', 'start', 'end', 'airlines'])) {
            return;
        }

        $this->period = $stored['period'] ?? $this->period;
        $this->start = $stored['start'] ?? null;
        $this->end = $stored['end'] ?? null;
        $this->airlines = $stored['airlines'] ?? [];
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

    /**
     * Jump to a quick range. Clears the custom bounds so the picker label and
     * the query string do not disagree with the range actually in effect.
     */
    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->start = null;
        $this->end = null;

        $this->refreshFilters();
    }

    public function applyCustomRange(): void
    {
        $this->period = self::PERIOD_CUSTOM;

        $this->refreshFilters();
    }

    public function clearAirlines(): void
    {
        $this->airlines = [];

        $this->refreshFilters();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(): array
    {
        $end = now()->endOfDay();

        return match ($this->period) {
            '7d'                => [now()->subDays(6)->startOfDay(), $end],
            '14d'               => [now()->subDays(13)->startOfDay(), $end],
            'ytd'               => [now()->startOfYear(), $end],
            self::PERIOD_CUSTOM => [
                filled($this->start) ? Carbon::parse($this->start)->startOfDay() : now()->subDays(29)->startOfDay(),
                filled($this->end) ? Carbon::parse($this->end)->endOfDay() : $end,
            ],
            default => [now()->subDays(29)->startOfDay(), $end],
        };
    }

    /**
     * Label shown on the closed period picker.
     */
    public function getPeriodLabel(): string
    {
        if ($this->period === self::PERIOD_CUSTOM) {
            [$start, $end] = $this->resolveRange();

            return $start->format('j M Y').' – '.$end->format('j M Y');
        }

        return static::getQuickRangeLabel($this->period);
    }

    public static function getQuickRangeLabel(string $period): string
    {
        return match ($period) {
            '7d'    => __('filament.reports_period_7d'),
            '14d'   => __('filament.reports_period_14d'),
            'ytd'   => __('filament.reports_period_ytd'),
            default => __('filament.reports_period_30d'),
        };
    }

    /**
     * Label shown on the closed airline picker.
     */
    public function getAirlinesLabel(): string
    {
        $selected = count($this->airlines);

        if ($selected === 0) {
            return __('filament.reports_all_airlines');
        }

        if ($selected === 1) {
            return $this->getAirlineOptions()[$this->airlines[0]] ?? __('filament.reports_all_airlines');
        }

        return trans_choice('filament.reports_airlines_selected', $selected, ['count' => $selected]);
    }

    /**
     * @return array<int|string, string>
     */
    public function getAirlineOptions(): array
    {
        return $this->airlineOptions ??= Airline::selectList(orderBy: 'name');
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
     * Resolve the URL state into the shape the widgets consume, and remember
     * it for the other report pages.
     */
    private function refreshFilters(): void
    {
        [$start, $end] = $this->resolveRange();

        $this->filters = [
            'start_date' => $start->toDateString(),
            'end_date'   => $end->toDateString(),
            'airlines'   => $this->airlines,
        ];

        session()->put(self::SESSION_KEY, [
            'period'   => $this->period,
            'start'    => $this->start,
            'end'      => $this->end,
            'airlines' => $this->airlines,
        ]);
    }
}
