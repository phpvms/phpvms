<?php

declare(strict_types=1);

namespace App\Filament\Resources\Fares\Pages;

use App\Filament\Concerns\PutsPrimaryActionLast;
use App\Filament\Resources\Fares\FareResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditFare extends EditRecord
{
    use PutsPrimaryActionLast;

    protected static string $resource = FareResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
