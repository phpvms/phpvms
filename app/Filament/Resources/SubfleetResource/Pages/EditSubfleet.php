<?php

namespace App\Filament\Resources\SubfleetResource\Pages;

use App\Filament\Resources\SubfleetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubfleet extends EditRecord
{
    protected static string $resource = SubfleetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
