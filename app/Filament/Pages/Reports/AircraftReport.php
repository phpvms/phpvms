<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\AircraftUtilizationChart;
use App\Filament\Widgets\FleetUtilizationTable;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Override;

/**
 * Aircraft report — utilization per aircraft and per subfleet (with CSV
 * export) in the selected period.
 */
class AircraftReport extends BaseReportPage
{
    protected static ?string $slug = 'reports/aircraft';

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Globe;

    protected static ?int $navigationSort = 3;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.reports_aircraft');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('filament.reports_aircraft');
    }

    public function getWidgets(): array
    {
        return [
            AircraftUtilizationChart::class,
            FleetUtilizationTable::class,
        ];
    }
}
