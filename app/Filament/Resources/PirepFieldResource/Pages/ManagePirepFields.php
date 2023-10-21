<?php

namespace App\Filament\Resources\PirepFieldResource\Pages;

use App\Filament\Resources\PirepFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePirepFields extends ManageRecords
{
    protected static string $resource = PirepFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Create Pirep Field')->modalHeading('Create Pirep Field'),
        ];
    }
}
