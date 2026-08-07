<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\PilotHoursChart;
use BackedEnum;
use Override;

/**
 * Pilots report — hours flown by pilot in the selected period.
 */
class PilotsReport extends BaseReportPage
{
    protected static ?string $slug = 'pilots';

    protected static string|BackedEnum|null $navigationIcon = 'tabler-users';

    protected static ?int $navigationSort = 2;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.reports_pilots');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('filament.reports_pilots');
    }

    public function getWidgets(): array
    {
        return [
            PilotHoursChart::class,
        ];
    }
}
