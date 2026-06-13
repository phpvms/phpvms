<?php

declare(strict_types=1);

namespace App\Filament\Resources\PirepFields\Pages;

use App\Filament\Resources\PirepFields\PirepFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Override;

class ManagePirepFields extends ManageRecords
{
    protected static string $resource = PirepFieldResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
