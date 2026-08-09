<?php

declare(strict_types=1);

namespace App\Filament\Resources\Typeratings\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Typeratings\TyperatingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditTyperating extends EditRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = TyperatingResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
