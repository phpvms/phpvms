<?php

namespace App\Filament\Resources\PirepFields\Pages;

use App\Filament\Resources\PirepFields\PirepFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManagePirepFields extends ManageRecords
{
    protected static string $resource = PirepFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
