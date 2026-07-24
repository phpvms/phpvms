<?php

declare(strict_types=1);

namespace App\Filament\Resources\Awards\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Awards\AwardResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateAward extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = AwardResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['image_file'])) {
            $data['image_url'] = $data['image_file'];
        }

        return $data;
    }
}
