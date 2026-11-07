<?php

namespace App\Filament\Resources\Airports\Pages;

use App\Filament\Concerns\HasRelationManagerTabs;
use App\Filament\Concerns\HasSaveBar;
use App\Filament\Resources\Airports\AirportResource;
use App\Models\Airport;
use App\Models\File;
use App\Services\FileService;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Tabs\Tab;
use Override;

class EditAirport extends EditRecord
{
    use HasRelationManagerTabs;
    use HasSaveBar;

    protected static string $resource = AirportResource::class;

    /**
     * Details, then a placeholder Runways tab (no relation behind it yet),
     * then the relation managers.
     *
     * @return array<string|int, Tab|null>
     */
    #[Override]
    public function getTabComponents(): array
    {
        $tabs = parent::getTabComponents();

        $runways = Tab::make(__('airports.runways'))
            ->icon(Phosphor::RoadHorizonLight)
            ->schema([
                EmptyState::make(__('airports.runways'))
                    ->icon(Phosphor::RoadHorizonLight)
                    ->description(__('airports.runways_placeholder')),
            ]);

        return array_slice($tabs, 0, 1, true) + ['runways' => $runways] + $tabs;
    }

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    protected function saveBarDeleteAction(): ?Action
    {
        return DeleteAction::make()->icon(Phosphor::TrashLight);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ForceDeleteAction::make()->icon(Phosphor::TrashSimpleLight)->before(function (Airport $record): void {
                $record->files()->each(function (File $file): void {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make()->icon(Phosphor::ArrowUUpLeftLight),
        ];
    }
}
