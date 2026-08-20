<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ranks\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Forms\Components\AssetImagePicker;
use App\Filament\Resources\Ranks\RankResource;
use App\Filament\Resources\Ranks\Schemas\RankForm;
use App\Models\Asset;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Override;

class CreateRank extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = RankResource::class;

    /**
     * The image control's state, held between the form resolving it and the
     * record existing to key an asset on.
     *
     * @var array<string, mixed>
     */
    private array $imageState = [];

    /**
     * The picker's keys are form state, not rank attributes: keep them out of
     * the create, and hold them for {@see afterCreate()}.
     */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $keys = AssetImagePicker::stateKeys(Asset::SLOT_RANK);

        $this->imageState = Arr::only($data, $keys);

        return Arr::except($data, $keys);
    }

    /** The rank has an id now, which is the key its asset is written under. */
    protected function afterCreate(): void
    {
        AssetImagePicker::persistState(
            Asset::SLOT_RANK,
            $this->getRecord()->getKey(),
            RankForm::imageDisk(),
            $this->imageState,
        );
    }

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }
}
