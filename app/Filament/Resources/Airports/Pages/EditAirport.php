<?php

namespace App\Filament\Resources\Airports\Pages;

use App\Filament\Concerns\HasRelationManagerTabs;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Airports\AirportResource;
use App\Filament\Resources\Airports\Schemas\AirportForm;
use App\Models\Airport;
use App\Models\File;
use App\Services\FileService;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Override;

class EditAirport extends EditRecord
{
    use HasRelationManagerTabs;
    use ReversePrimaryButtons;

    protected static string $resource = AirportResource::class;

    /**
     * One section only, so no jump bar.
     *
     * @return array<string, string>
     */
    protected function jumpBarFormSections(): array
    {
        return [];
    }

    /** The list modals share AirportForm bare; the full page sets it in a section. */
    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.airport_information'))
                ->id('airport-information')
                ->schema(AirportForm::components())
                ->columnSpanFull(),
        ]);
    }

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

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->icon(Phosphor::TrashLight),
            ForceDeleteAction::make()->icon(Phosphor::TrashSimpleLight)->before(function (Airport $record): void {
                $record->files()->each(function (File $file): void {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make()->icon(Phosphor::ArrowUUpLeftLight),
        ];
    }
}
