<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

/**
 * Reports admin page — hub for operational reports (PIREP history, finances,
 * fleet utilization). Currently a placeholder shell; report sections will be
 * added here as they ship.
 */
class Reports extends Page
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.pages.reports';

    #[Override]
    public function getTitle(): string
    {
        return __('filament.reports');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.reports');
    }
}
