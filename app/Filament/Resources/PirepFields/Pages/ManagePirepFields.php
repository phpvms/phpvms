<?php

declare(strict_types=1);

namespace App\Filament\Resources\PirepFields\Pages;

use App\Filament\Resources\PirepFields\PirepFieldResource;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Override;

class ManagePirepFields extends ManageRecords
{
    protected static string $resource = PirepFieldResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Phosphor::PlusCircleLight),
        ];
    }
}
