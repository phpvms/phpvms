<?php

declare(strict_types=1);

namespace App\Filament\Resources\SimBriefAirframes\Pages;

use App\Filament\Resources\SimBriefAirframes\SimBriefAirframeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageSimBriefAirframes extends ManageRecords
{
    protected static string $resource = SimBriefAirframeResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
