<?php

declare(strict_types=1);

namespace App\Filament\Resources\Awards\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Awards\AwardResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditAward extends EditRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = AwardResource::class;

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
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (array_key_exists('image_url', $data) && str_starts_with((string) $data['image_url'], 'awards/')) {
            $data['image_file'] = $data['image_url'];
            unset($data['image_url']);
        }

        return $data;
    }

    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['image_file'])) {
            $data['image_url'] = $data['image_file'];
        }

        return $data;
    }
}
