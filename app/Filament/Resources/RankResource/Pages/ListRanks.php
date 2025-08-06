<?php

namespace App\Filament\Resources\RankResource\Pages;

use App\Filament\Resources\RankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRanks extends ListRecords
{
    protected static string $resource = RankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Rank')->icon('heroicon-o-plus-circle'),
        ];
    }
}
