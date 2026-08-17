<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\PilotHoursChart;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Override;

/**
 * Pilots report — hours flown by pilot in the selected period.
 */
class PilotsReport extends BaseReportPage
{
    protected static ?string $slug = 'reports/pilots';

    protected static string|BackedEnum|null $navigationIcon = Phosphor::UsersLight;

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
