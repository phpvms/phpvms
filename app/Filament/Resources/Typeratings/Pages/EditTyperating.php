<?php

declare(strict_types=1);

namespace App\Filament\Resources\Typeratings\Pages;

use App\Filament\Resources\Typeratings\TyperatingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditTyperating extends EditRecord
{
    protected static string $resource = TyperatingResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
