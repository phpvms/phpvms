<?php

namespace App\Filament\Resources\FlightBundles\RelationManagers;

use App\Enums\ImportExportType;
use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\FlightExporter;
use App\Filament\Imports\FlightImporter;
use App\Filament\Resources\FlightBundles\Resources\Flight\FlightResource;
use App\Filament\Resources\FlightBundles\Resources\Flight\Tables\FlightsTable;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

class FlightsRelationManager extends RelationManager
{
    protected static string $relationship = 'flights';

    protected static ?string $relatedResource = FlightResource::class;

    public function table(Table $table): Table
    {
        return FlightsTable::configure($table)
            ->headerActions([
                OldExportAction::make('old-export')
                    ->visible(fn (): bool => !config('phpvms.use_queued_filament_imports'))
                    ->arguments([
                        'resourceTitle' => 'flights',
                        'exportType'    => ImportExportType::FLIGHTS,
                    ]),

                OldImportAction::make('old-import')
                    ->visible(fn (): bool => !config('phpvms.use_queued_filament_imports'))
                    ->arguments([
                        'resourceTitle' => 'flights',
                        'importType'    => ImportExportType::FLIGHTS,
                    ]),

                ImportAction::make('import')
                    ->visible(config('phpvms.use_queued_filament_imports'))
                    ->importer(FlightImporter::class),

                ExportAction::make('export')
                    ->visible(config('phpvms.use_queued_filament_imports'))
                    ->exporter(FlightExporter::class),

                CreateAction::make()
                    ->icon(Heroicon::OutlinedPlusCircle),
            ]);
    }

    #[Override]
    protected static function getModelLabel(): string
    {
        return trans_choice('common.flight', 1);
    }
}
