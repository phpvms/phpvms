<?php

namespace App\Filament\Resources\Subfleets\RelationManagers;

use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\AircraftExporter;
use App\Filament\Imports\AircraftImporter;
use App\Filament\Resources\Subfleets\Resources\Aircraft\AircraftResource;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Tables\AircraftTable;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AircraftRelationManager extends RelationManager
{
    protected static string $relationship = 'aircraft';

    protected static ?string $relatedResource = AircraftResource::class;

    public function table(Table $table): Table
    {
        return AircraftTable::configure($table)
            ->headerActions([
                OldExportAction::make('export')
                    ->arguments([
                        'resourceTitle' => 'aircraft',
                        'exportType'    => ImportExportType::AIRCRAFT,
                    ]),

                OldImportAction::make('import')
                    ->arguments([
                        'resourceTitle' => 'aircraft',
                        'importType'    => ImportExportType::AIRCRAFT,
                    ]),

                ImportAction::make('import')
                    ->visible(config('phpvms.use_queued_filament_imports'))
                    ->importer(AircraftImporter::class),

                ExportAction::make('export')
                    ->visible(config('phpvms.use_queued_filament_imports'))
                    ->exporter(AircraftExporter::class),

                CreateAction::make()
                    ->icon(Heroicon::OutlinedPlusCircle),
            ]);
    }

    public static function getModelLabel(): string
    {
        return __('common.aircraft');
    }
}
