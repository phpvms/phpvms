<?php

namespace App\Filament\Resources\UserFieldResource\Pages;

use App\Filament\Resources\UserFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUserFields extends ManageRecords
{
    protected static string $resource = UserFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add User Field')->icon('heroicon-o-plus-circle')->modalHeading('Add User Field'),
        ];
    }
}
