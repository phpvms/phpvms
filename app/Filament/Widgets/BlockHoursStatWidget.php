<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Override;

class BlockHoursStatWidget extends DashboardStatWidget
{
    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.block_hours');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 2;
    }

    #[Override]
    protected function getViewData(): array
    {
        $reports = $this->recentReports();
        $legsCount = (clone $reports)->count();
        $blockMinutes = (int) (clone $reports)->sum('flight_time');

        return [
            'label'    => static::getWidgetLabel(),
            'value'    => number_format($blockMinutes / 60, 1),
            'suffix'   => 'h',
            'note'     => $legsCount.' legs · '.number_format($legsCount > 0 ? $blockMinutes / $legsCount / 60 : 0, 1).' h average',
            'icon'     => 'phosphor-clock-light',
            'accent'   => 'blue',
            'noteIcon' => null,
        ];
    }
}
