<?php

namespace App\Filament\Actions;

use App\Models\Enums\ImportExportType;
use App\Services\ImportService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ImportAction extends Action
{
    use CanCustomizeProcess;

    public static function getDefaultName(): ?string
    {
        return 'import';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Import from CSV');

        $this->schema([
            FileUpload::make('importFile')->acceptedFileTypes(['text/csv'])->disk('local')->directory('import'),
            Toggle::make('deletePrevious')->label('Delete Existing Data')->default(false),
        ]);

        $this->action(function (array $data, array $arguments): void {
            if (!isset($arguments['resourceTitle']) || !$arguments['importType']) {
                $this->failure();

                return;
            }

            $importSvc = app(ImportService::class);

            $path = storage_path('app/'.$data['importFile']);
            Log::info('Uploaded '.$arguments['resourceTitle'].' import file to '.$path);

            switch ($arguments['importType']) {
                case ImportExportType::AIRCRAFT:
                    $logs = $importSvc->importAircraft($path, $data['deletePrevious']);
                    break;
                case ImportExportType::AIRPORT:
                    $logs = $importSvc->importAirports($path, $data['deletePrevious']);
                    break;
                case ImportExportType::EXPENSES:
                    $logs = $importSvc->importExpenses($path, $data['deletePrevious']);
                    break;
                case ImportExportType::FARES:
                    $logs = $importSvc->importFares($path, $data['deletePrevious']);
                    break;
                case ImportExportType::FLIGHTS:
                    $logs = $importSvc->importFlights($path, $data['deletePrevious']);
                    break;
                case ImportExportType::SUBFLEETS:
                    $logs = $importSvc->importSubfleets($path, $data['deletePrevious']);
                    break;
            }

            if (!isset($logs)) {
                $this->failure();

                return;
            }

            if (count($logs['errors']) > 0) {
                Notification::make()
                    ->title('There were '.count($logs['errors']).' errors importing '.$arguments['resourceTitle'])
                    ->body(implode('<br>', $logs['errors']))
                    ->persistent()
                    ->actions([
                        Action::make('close')->label('Close')->close(),
                    ])
                    ->danger()
                    ->send();
            }

            if (count($logs['success']) > 0) {
                Notification::make()
                    ->title(count($logs['success']).' '.$arguments['resourceTitle'].' imported successfully')
                    ->success()
                    ->send();
            }
        });

        $this->modalHeading('Import from CSV');

        $this->modalSubmitActionLabel('Import');

        $this->icon('heroicon-o-document-arrow-up');

        $this->groupedIcon('heroicon-m-document-arrow-up');
    }
}
