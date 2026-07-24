<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\PutsPrimaryActionLast;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use PutsPrimaryActionLast;

    protected static string $resource = PageResource::class;
}
