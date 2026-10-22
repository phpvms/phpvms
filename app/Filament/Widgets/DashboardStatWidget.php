<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Filament\Concerns\IsDynamicDashboardWidget;
use App\Filament\Concerns\ReadsPageFilters;
use App\Models\Pirep;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;

abstract class DashboardStatWidget extends Widget implements DynamicWidget
{
    use IsDynamicDashboardWidget;
    use ReadsPageFilters;

    protected string $view = 'filament.widgets.dashboard.stat-card';

    protected int|string|array $columnSpan = 'full';

    /**
     * One row. The card carries three lines (label, value, note) at the
     * stats-strip type sizes and needs a ~74px content box; the flat-12
     * template's 100px row leaves exactly that once GridStack's 12px margin
     * and the widget body's border are taken off.
     */
    public static function getDynamicDashboardDefaultHeight(): int
    {
        return 1;
    }

    public static function getDynamicDashboardMinWidth(): int
    {
        return static::getDynamicDashboardDefaultWidth();
    }

    public static function getDynamicDashboardMaxWidth(): int
    {
        return static::getDynamicDashboardDefaultWidth();
    }

    public static function getDynamicDashboardMinHeight(): int
    {
        return static::getDynamicDashboardDefaultHeight();
    }

    public static function getDynamicDashboardMaxHeight(): int
    {
        return static::getDynamicDashboardDefaultHeight();
    }

    /**
     * Every filed report in the page's selected period.
     *
     * Scoped to `submitted_at` rather than `created_at` because a PIREP is
     * created at prefile and only becomes a *report* when it is filed, which
     * can be days later.
     *
     * @return Builder<Pirep>
     */
    protected function recentReports(): Builder
    {
        $airlines = $this->filterAirlines();

        return Pirep::query()
            ->whereBetween('submitted_at', [$this->filterStartDate(), $this->filterEndDate()])
            ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED])
            ->when(
                filled($airlines),
                fn (Builder $query): Builder => $query->whereIn('airline_id', $airlines),
            );
    }
}
