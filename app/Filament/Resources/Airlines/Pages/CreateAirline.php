<?php

declare(strict_types=1);

namespace App\Filament\Resources\Airlines\Pages;

use App\Filament\Concerns\PutsPrimaryActionLast;
use App\Filament\Resources\Airlines\AirlineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAirline extends CreateRecord
{
    use PutsPrimaryActionLast;

    protected static string $resource = AirlineResource::class;
}
