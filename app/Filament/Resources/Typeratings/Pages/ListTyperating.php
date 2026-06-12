<?php

declare(strict_types=1);

namespace App\Filament\Resources\Typeratings\Pages;

use App\Filament\Resources\Typeratings\TyperatingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Override;

class ListTyperating extends ListRecords
{
    protected static string $resource = TyperatingResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
