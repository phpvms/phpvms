<?php

namespace App\Filament\Resources\Typeratings\Pages;

use App\Filament\Resources\Typeratings\TyperatingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTyperating extends EditRecord
{
    protected static string $resource = TyperatingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
