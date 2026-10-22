<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\Tours\TourResource;

/**
 * The bundle edit page under the tours nav item: same overview, leg warning,
 * live-runs panel, flights table and Forge action. Only the drawer differs —
 * it has no type select, so the record cannot be saved out of this page's
 * scope.
 */
class EditTour extends EditFlightBundle
{
    protected static string $resource = TourResource::class;

    protected static bool $forTours = true;
}
