<?php

namespace App\Filament\Resources\Airports\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Resources\Airports\AirportResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAirports extends ListRecords
{
    protected static string $resource = AirportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')
                ->arguments(['resourceTitle' => 'airports', 'exportType' => ImportExportType::AIRPORT]),

            ImportAction::make('import')
                ->arguments(['resourceTitle' => 'airports', 'importType' => ImportExportType::AIRPORT]),
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
