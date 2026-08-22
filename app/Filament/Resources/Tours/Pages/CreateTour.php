<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Resources\FlightBundles\Pages\CreateFlightBundle;
use App\Filament\Resources\Tours\TourResource;

/**
 * The type is not asked for here — FlightBundleForm's tours variant carries it
 * as a hidden field defaulted to `tour`.
 */
class CreateTour extends CreateFlightBundle
{
    protected static string $resource = TourResource::class;
}
