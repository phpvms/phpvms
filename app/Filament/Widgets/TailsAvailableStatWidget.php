<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AircraftStatus;
use App\Models\Aircraft;
use Override;

class TailsAvailableStatWidget extends DashboardStatWidget
{
    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.tails_available');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 3;
    }

    #[Override]
    protected function getViewData(): array
    {
        $maintenanceCount = Aircraft::query()->where('status', AircraftStatus::MAINTENANCE)->count();

        return [
            'label'    => static::getWidgetLabel(),
            'value'    => number_format(Aircraft::query()->where('status', AircraftStatus::ACTIVE)->count()),
            'suffix'   => 'of '.number_format(Aircraft::query()->count()),
            'note'     => $maintenanceCount > 0 ? $maintenanceCount.' in maintenance' : null,
            'icon'     => 'tabler-box',
            'accent'   => 'amber',
            'noteIcon' => $maintenanceCount > 0 ? 'tabler-tool' : null,
        ];
    }
}
