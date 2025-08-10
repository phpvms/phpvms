<?php

namespace App\Filament\Resources\UserFields\Pages;

use App\Filament\Resources\UserFields\UserFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageUserFields extends ManageRecords
{
    protected static string $resource = UserFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
