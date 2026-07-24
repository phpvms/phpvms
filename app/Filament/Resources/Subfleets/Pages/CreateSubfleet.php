<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subfleets\Pages;

use App\Filament\Concerns\PutsPrimaryActionLast;
use App\Filament\Resources\Subfleets\SubfleetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubfleet extends CreateRecord
{
    use PutsPrimaryActionLast;

    protected static string $resource = SubfleetResource::class;
}
