<?php

namespace App\Filament\Resources\AwardResource\Pages;

use App\Filament\Resources\AwardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAwards extends ListRecords
{
    protected static string $resource = AwardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Award')->icon('heroicon-o-plus-circle'),
        ];
    }
}
