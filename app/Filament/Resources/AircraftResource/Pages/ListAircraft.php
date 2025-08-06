<?php

namespace App\Filament\Resources\AircraftResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Resources\AircraftResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAircraft extends ListRecords
{
    protected static string $resource = AircraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')->arguments(['resourceTitle' => 'aircraft', 'exportType' => ImportExportType::AIRCRAFT]),
            ImportAction::make('import')->arguments(['resourceTitle' => 'aircraft', 'importType' => ImportExportType::AIRCRAFT]),
            CreateAction::make()->label('Add Aircraft')->icon('heroicon-o-plus-circle'),
        ];
    }
}
