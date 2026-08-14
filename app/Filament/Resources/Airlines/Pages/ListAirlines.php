<?php

declare(strict_types=1);

namespace App\Filament\Resources\Airlines\Pages;

use App\Filament\Resources\Airlines\AirlineResource;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListAirlines extends ListRecords
{
    protected static string $resource = AirlineResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(TablerIcon::CirclePlus),
        ];
    }
}
