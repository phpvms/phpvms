<?php

namespace App\Filament\Resources\TypeRatingResource\Pages;

use App\Filament\Resources\TyperatingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTypeRatings extends ListRecords
{
    protected static string $resource = TyperatingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Type Rating')->icon('heroicon-o-plus-circle'),
        ];
    }
}
