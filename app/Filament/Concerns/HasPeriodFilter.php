<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Airline;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * The period + airline selection behind the filter popout, shared by the
 * Reports pages and the Dashboard.
 *
 * Quick ranges are held relative ("30d") rather than as resolved dates, so a
 * bookmarked page keeps meaning "the last 30 days" instead of freezing to
 * whatever dates were current when it was saved. Only `custom` reads
 * {@see $start} and {@see $end}.
 *
 * The resolved shape is the `start_date` / `end_date` / `airlines` set that
 * every widget consumes through {@see ReadsPageFilters}.
 */
trait HasPeriodFilter
{
    public const string PERIOD_CUSTOM = 'custom';

    /** @var array<int, string> */
    public const array PERIODS = ['1d', '7d', '14d', '30d', 'ytd'];

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
     * `Airline::selectList()` is an uncached query and the header asks for it
     * from both the picker and the subheading, so hold it for the request.
     *
     * @var array<int|string, string>|null
     */
    private ?array $airlineOptions = null;

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
            '1d'                => [now()->startOfDay(), $end],
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
            '1d'    => __('filament.reports_period_1d'),
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

    /**
     * The selection resolved into the shape widgets consume.
     *
     * @return array{start_date: string, end_date: string, airlines: array<int, string>}
     */
    public function resolvedPeriodFilters(): array
    {
        [$start, $end] = $this->resolveRange();

        return [
            'start_date' => $start->toDateString(),
            'end_date'   => $end->toDateString(),
            'airlines'   => $this->airlines,
        ];
    }

    /**
     * The state worth carrying between visits. Kept relative, for the same
     * reason the quick ranges are.
     *
     * @return array{period: string, start: string|null, end: string|null, airlines: array<int, string>}
     */
    protected function periodFilterState(): array
    {
        return [
            'period'   => $this->period,
            'start'    => $this->start,
            'end'      => $this->end,
            'airlines' => $this->airlines,
        ];
    }

    /**
     * Restore a previously stored selection. Anything in the query string is an
     * explicit choice and wins over what a previous visit left behind.
     *
     * @param mixed $stored Whatever came out of the session.
     */
    protected function restorePeriodFilterState(mixed $stored): void
    {
        if (!is_array($stored) || request()->hasAny(['period', 'start', 'end', 'airlines'])) {
            return;
        }

        $this->period = $stored['period'] ?? $this->period;
        $this->start = $stored['start'] ?? null;
        $this->end = $stored['end'] ?? null;
        $this->airlines = $stored['airlines'] ?? [];
    }

    /**
     * Rebuild the resolved filter set after the selection changes. Implemented
     * by the page, which decides where the resolved set and the stored state go.
     */
    abstract protected function refreshFilters(): void;
}
