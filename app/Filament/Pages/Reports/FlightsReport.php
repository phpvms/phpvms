<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Filament\Widgets\DistanceFlownChart;
use App\Filament\Widgets\HoursFlownChart;
use App\Filament\Widgets\LandingRateChart;
use App\Filament\Widgets\PirepHistoryTable;
use App\Filament\Widgets\PirepStateChart;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Override;

/**
 * Flights report — every PIREP in the selected period plus the flight
 * stat breakdown (hours, distance, landing rate, state mix).
 */
class FlightsReport extends BaseReportPage
{
    protected static ?string $slug = 'flights';

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Plane;

    protected static ?int $navigationSort = 1;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.reports_flights');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('filament.reports_flights');
    }

    public function getWidgets(): array
    {
        return [
            PirepHistoryTable::class,
            HoursFlownChart::class,
            DistanceFlownChart::class,
            LandingRateChart::class,
            PirepStateChart::class,
        ];
    }
}
