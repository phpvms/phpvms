<?php

declare(strict_types=1);

namespace App\Filament\Resources\NavigationItems\Pages;

use App\Filament\Resources\NavigationItems\NavigationItemResource;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListNavigationItems extends ListRecords
{
    protected static string $resource = NavigationItemResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon(Phosphor::PlusCircleLight),
        ];
    }
}
