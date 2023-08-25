<?php

namespace App\Filament\Resources\FlightResource\Pages;

use App\Filament\Resources\FlightResource;
use App\Models\Enums\ImportExportType;
use App\Services\ImportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\FileUpload;
use App\Http\Controllers\Admin\Traits\Importable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ListFlights extends ListRecords
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Export to CSV'),
            Actions\Action::make('import')->label('Import from CSV')->form([
                FileUpload::make('importFile')->maxSize(1024 * 1024 * 10)->acceptedFileTypes(['text/csv'])->directory('import'),
            ])->action(function (array $data): void {
                dd($data);
                /*$path = Storage::putFileAs(
                    'import',
                    $data['file'],
                    'import_'.ImportExportType::label(ImportExportType::FLIGHTS).'.csv'
                );

                $importSvc = app(ImportService::class);

                $path = storage_path('app/'.$path);
                Log::info('Uploaded airport import file to '.$path);

                $importSvc->importFlights($path, true);*/
            }),
            Actions\CreateAction::make()->label('Add Flight'),
        ];
    }
}
