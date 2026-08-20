<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ranks\Pages;

use App\Filament\Concerns\AutosavesFields;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Forms\Components\AssetImagePicker;
use App\Filament\Resources\Ranks\RankResource;
use App\Filament\Resources\Ranks\Schemas\RankForm;
use App\Models\Asset;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditRank extends EditRecord
{
    use AutosavesFields;
    use ReversePrimaryButtons;

    protected static string $resource = RankResource::class;

    /**
     * Only the image control autosaves; the rest of the form saves on submit.
     * The picker wires itself through its own afterStateUpdated hook.
     *
     * @return list<string>
     */
    protected function autosaveKeys(): array
    {
        return AssetImagePicker::stateKeys(Asset::SLOT_RANK);
    }

    protected function persistAutosavedField(string $key, mixed $value): void
    {
        AssetImagePicker::persist(
            Asset::SLOT_RANK,
            $this->getRecord()->getKey(),
            RankForm::imageDisk(),
            $key,
            $value,
        );
    }

    protected function autosaveNotificationTitle(): string
    {
        return __('filament.rank_image_saved');
    }

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->icon(Phosphor::TrashLight),
            ForceDeleteAction::make()->icon(Phosphor::TrashSimpleLight),
            RestoreAction::make()->icon(Phosphor::ArrowUUpLeftLight),
        ];
    }
}
