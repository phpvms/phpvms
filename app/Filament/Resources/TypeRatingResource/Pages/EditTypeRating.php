<?php

namespace App\Filament\Resources\TypeRatingResource\Pages;

use App\Filament\Resources\TyperatingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTypeRating extends EditRecord
{
    protected static string $resource = TyperatingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
