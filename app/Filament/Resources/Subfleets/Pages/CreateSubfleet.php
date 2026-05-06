<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subfleets\Pages;

use App\Filament\Resources\Subfleets\SubfleetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubfleet extends CreateRecord
{
    protected static string $resource = SubfleetResource::class;
}
