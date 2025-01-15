<?php

namespace App\Filament\Actions;

use App\Models\Enums\ImportExportType;
use App\Repositories\AircraftRepository;
use App\Repositories\AirportRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\FareRepository;
use App\Repositories\FlightRepository;
use App\Repositories\SubfleetRepository;
use App\Services\ExportService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportAction extends Action
{
    use CanCustomizeProcess;

    public static function getDefaultName(): ?string
    {
        return 'export';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Export to CSV');

        $this->action(function (array $arguments): ?BinaryFileResponse {
            if (!isset($arguments['resourceTitle']) || !$arguments['exportType']) {
                $this->failure();

                return null;
            }

            $exportSvc = app(ExportService::class);

            $file_name = $arguments['resourceTitle'].'.csv';

            switch ($arguments['exportType']) {
                case ImportExportType::AIRCRAFT:
                    $data = app(AircraftRepository::class)->orderBy('registration')->get();
                    $path = $exportSvc->exportAircraft($data);
                    break;
                case ImportExportType::AIRPORT:
                    $data = app(AirportRepository::class)->all();
                    $path = $exportSvc->exportAirports($data);
                    break;
                case ImportExportType::EXPENSES:
                    $data = app(ExpenseRepository::class)->all();
                    $path = $exportSvc->exportExpenses($data);
                    break;
                case ImportExportType::FARES:
                    $data = app(FareRepository::class)->all();
                    $path = $exportSvc->exportFares($data);
                    break;
                case ImportExportType::FLIGHTS:
                    $data = app(FlightRepository::class)->orderBy('airline_id')->orderBy('flight_number')->orderBy('route_code')->orderBy('route_leg')->get();
                    $path = $exportSvc->exportFlights($data);
                    break;
                case ImportExportType::SUBFLEETS:
                    $data = app(SubfleetRepository::class)->all();
                    $path = $exportSvc->exportSubfleets($data);
                    break;
            }

            $this->sendSuccessNotification();

            return response()->download($path, $file_name, ['content-type' => 'text/csv'])->deleteFileAfterSend(true);
        });

        $this->successNotificationTitle('Data exported successfully');

        $this->modalHeading('Export to CSV');

        $this->modalSubmitActionLabel('Export');

        $this->icon('heroicon-o-document-arrow-down');

        $this->groupedIcon('heroicon-m-document-arrow-down');
    }
}
