<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Enums\NavigationGroup;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Clusters\Cluster;
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

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Report;

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.reports');
    }
}
