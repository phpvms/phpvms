<?php

declare(strict_types=1);

namespace App\Filament\Resources\Typeratings\Pages;

use App\Filament\Concerns\HasRelationManagerTabs;
use App\Filament\Concerns\HasSaveBar;
use App\Filament\Resources\Typeratings\TyperatingResource;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditTyperating extends EditRecord
{
    use HasRelationManagerTabs;
    use HasSaveBar;

    protected static string $resource = TyperatingResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    protected function saveBarDeleteAction(): ?Action
    {
        return DeleteAction::make()->icon(Phosphor::TrashLight);
    }
}
