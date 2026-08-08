<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlightBundles\Pages;

use App\Filament\Resources\FlightBundles\FlightBundleResource;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListFlightBundles extends ListRecords
{
    protected static string $resource = FlightBundleResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon(TablerIcon::CirclePlus),
        ];
    }
}
