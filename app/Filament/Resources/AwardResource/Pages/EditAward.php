<?php

namespace App\Filament\Resources\AwardResource\Pages;

use App\Filament\Resources\AwardResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAward extends EditRecord
{
    protected static string $resource = AwardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (array_key_exists('image_url', $data) && str_starts_with($data['image_url'], 'awards/')) {
            $data['image_file'] = $data['image_url'];
            unset($data['image_url']);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['image_file'])) {
            $data['image_url'] = $data['image_file'];
        }

        return $data;
    }
}
