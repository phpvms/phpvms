<?php

namespace App\Filament\Resources\FlightResource\Pages;

use App\Filament\Resources\FlightResource;
use App\Repositories\FlightRepository;
use App\Services\ExportService;
use App\Services\ImportService;
use Filament\Actions;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListFlights extends ListRecords
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')->label('Export to CSV')
                ->action(function (): BinaryFileResponse {
                    $exporter = app(ExportService::class);
                    $flightRepo = app(FlightRepository::class);

                    $where = [];
                    $file_name = 'flights.csv';
                    $flights = $flightRepo->where($where)->orderBy('airline_id')->orderBy('flight_number')->orderBy('route_code')->orderBy('route_leg')->get();

                    $path = $exporter->exportFlights($flights);

                    return response()->download($path, $file_name, ['content-type' => 'text/csv'])->deleteFileAfterSend(true);
                })->after(function (): void {
                    Notification::make()
                        ->title('Flights Exported')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('import')->label('Import from CSV')
                ->form([
                    FileUpload::make('importFile')->acceptedFileTypes(['text/csv'])->disk('local')->directory('import'),
                    Toggle::make('delete')->label('Delete Previous Flights')->default(false),
                ])->action(function (array $data): void {
                    $importSvc = app(ImportService::class);

                    $path = storage_path('app/'.$data['importFile']);
                    Log::info('Uploaded airport import file to '.$path);

                    $importSvc->importFlights($path, $data['delete']);
                }),
            Actions\CreateAction::make()->label('Add Flight'),
        ];
    }
}
