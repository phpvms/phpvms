<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlightBundles\Pages;

use App\Filament\Resources\FlightBundles\FlightBundleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditFlightBundle extends EditRecord
{
    protected static string $resource = FlightBundleResource::class;

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
