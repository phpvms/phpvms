<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Resources\FlightBundles\Pages\ListFlightBundles;
use App\Filament\Resources\Tours\TourResource;

class ListTours extends ListFlightBundles
{
    protected static string $resource = TourResource::class;

    protected static bool $forTours = true;
}
