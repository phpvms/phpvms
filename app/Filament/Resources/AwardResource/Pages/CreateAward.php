<?php

namespace App\Filament\Resources\AwardResource\Pages;

use App\Filament\Resources\AwardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAward extends CreateRecord
{
    protected static string $resource = AwardResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['image_file'])) {
            $data['image_url'] = $data['image_file'];
        }

        return $data;
    }
}
