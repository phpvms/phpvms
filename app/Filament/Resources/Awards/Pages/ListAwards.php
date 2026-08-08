<?php

declare(strict_types=1);

namespace App\Filament\Resources\Awards\Pages;

use App\Filament\Resources\Awards\AwardResource;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListAwards extends ListRecords
{
    protected static string $resource = AwardResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(TablerIcon::CirclePlus),
        ];
    }
}
