<?php

declare(strict_types=1);

namespace App\Filament\Resources\AwardSnippets\Pages;

use App\Filament\Resources\AwardSnippets\AwardSnippetResource;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Override;

class ManageAwardSnippets extends ManageRecords
{
    protected static string $resource = AwardSnippetResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Phosphor::PlusCircleLight)
                // The rule builder needs the room.
                ->modalWidth(Width::FiveExtraLarge),
        ];
    }
}
