<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Override;

class DistanceStatWidget extends DashboardStatWidget
{
    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.distance');
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
        $distance = (int) round((float) (clone $reports)->sum('distance'));

        return [
            'label'    => static::getWidgetLabel(),
            'value'    => number_format($distance),
            'suffix'   => 'nm',
            'note'     => number_format($legsCount > 0 ? (int) round($distance / $legsCount) : 0).' nm average leg',
            'icon'     => 'phosphor-ruler-light',
            'accent'   => 'teal',
            'noteIcon' => null,
        ];
    }
}
