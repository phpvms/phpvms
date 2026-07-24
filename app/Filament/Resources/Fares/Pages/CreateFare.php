<?php

declare(strict_types=1);

namespace App\Filament\Resources\Fares\Pages;

use App\Filament\Concerns\PutsPrimaryActionLast;
use App\Filament\Resources\Fares\FareResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFare extends CreateRecord
{
    use PutsPrimaryActionLast;

    protected static string $resource = FareResource::class;
}
