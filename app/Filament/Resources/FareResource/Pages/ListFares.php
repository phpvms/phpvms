<?php

namespace App\Filament\Resources\FareResource\Pages;

use App\Filament\Resources\FareResource;
use App\Repositories\FareRepository;
use App\Services\ExportService;
use App\Services\ImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListFares extends ListRecords
{
    protected static string $resource = FareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')->label('Export to CSV')
            ->action(function (): BinaryFileResponse {
                $exporter = app(ExportService::class);
                $fareRepo = app(FareRepository::class);
                $fares = $fareRepo->all();

                $path = $exporter->exportFares($fares);
                return response()->download($path, 'fares.csv', ['content-type' => 'text/csv'])->deleteFileAfterSend(true);
            })->after(function (): void {
                Notification::make()
                    ->title('Fares Exported')
                    ->success()
                    ->send();
            }),
            Actions\Action::make('import')->label('Import from CSV')
                ->form([
                    FileUpload::make('importFile')->acceptedFileTypes(['text/csv'])->disk('local')->directory('import'),
                    Toggle::make('delete')->label('Delete Previous Fares')->default(false),
                ])->action(function (array $data, Actions\Action $action): void {
                $importSvc = app(ImportService::class);

                $path = storage_path('app/'.$data['importFile']);
                Log::info('Uploaded Fare import file to '.$path);

                $logs = $importSvc->importFares($path, $data['delete']);

                session(['logs' => $logs]);

                if (count($logs['errors']) === 0) {
                    Notification::make()
                        ->title('Fares Imported Successfully')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('There were '.count($logs['errors']).' errors importing the fares')
                        ->body(implode('<br>', $logs['errors']))
                        ->persistent()
                        ->danger()
                        ->send();
                }
            }),
            Actions\CreateAction::make()->label('Add Fare'),
        ];
    }
}
