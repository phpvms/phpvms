<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ranks\Pages;

use App\Filament\Concerns\PutsPrimaryActionLast;
use App\Filament\Resources\Ranks\RankResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRank extends CreateRecord
{
    use PutsPrimaryActionLast;

    protected static string $resource = RankResource::class;
}
