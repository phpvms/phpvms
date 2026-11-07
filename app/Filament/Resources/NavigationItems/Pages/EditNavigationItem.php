<?php

declare(strict_types=1);

namespace App\Filament\Resources\NavigationItems\Pages;

use App\Filament\Concerns\HasSaveBar;
use App\Filament\Resources\NavigationItems\NavigationItemResource;
use App\Models\NavigationItem;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditNavigationItem extends EditRecord
{
    use HasSaveBar;

    protected static string $resource = NavigationItemResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    protected function saveBarDeleteAction(): ?Action
    {
        return DeleteAction::make()
            ->icon(Phosphor::TrashLight)
            ->hidden(fn (NavigationItem $record): bool => $record->children()->exists());
    }
}
