<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

/**
 * Reports hub — navigation parent for the Flights, Pilots and Aircraft
 * report sub-pages. Clicking the parent redirects to the first child.
 */
class Reports extends Cluster
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.reports');
    }
}
