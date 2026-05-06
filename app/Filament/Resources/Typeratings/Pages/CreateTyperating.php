<?php

declare(strict_types=1);

namespace App\Filament\Resources\Typeratings\Pages;

use App\Filament\Resources\Typeratings\TyperatingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTyperating extends CreateRecord
{
    protected static string $resource = TyperatingResource::class;
}
