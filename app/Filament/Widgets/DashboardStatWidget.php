<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Filament\Concerns\IsDynamicDashboardWidget;
use App\Models\Pirep;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;

abstract class DashboardStatWidget extends Widget implements DynamicWidget
{
    use IsDynamicDashboardWidget;

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

    /** @return Builder<Pirep> */
    protected function recentReports(): Builder
    {
        return Pirep::query()
            ->where('submitted_at', '>=', now()->subDays(6)->startOfDay())
            ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED]);
    }
}
