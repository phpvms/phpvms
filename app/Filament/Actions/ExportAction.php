<?php

namespace App\Filament\Actions;

use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Enums\ImportExportType;
use App\Models\Expense;
use App\Models\Fare;
use App\Models\Subfleet;
use App\Repositories\FlightRepository;
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

        $this->visible(!config('phpvms.use_queued_filament_imports'));

        $this->action(function (array $arguments): ?BinaryFileResponse {
            if (!isset($arguments['resourceTitle']) || !$arguments['exportType']) {
                $this->failure();

                return null;
            }

            $exportSvc = app(ExportService::class);

            $file_name = $arguments['resourceTitle'].'.csv';

            switch ($arguments['exportType']) {
                case ImportExportType::AIRCRAFT:
                    $data = Aircraft::orderBy('registration')->get();
                    $path = $exportSvc->exportAircraft($data);
                    break;
                case ImportExportType::AIRPORT:
                    $data = Airport::all();
                    $path = $exportSvc->exportAirports($data);
                    break;
                case ImportExportType::EXPENSES:
                    $data = Expense::all();
                    $path = $exportSvc->exportExpenses($data);
                    break;
                case ImportExportType::FARES:
                    $data = Fare::all();
                    $path = $exportSvc->exportFares($data);
                    break;
                case ImportExportType::FLIGHTS:
                    $data = app(FlightRepository::class)->orderBy('airline_id')->orderBy('flight_number')->orderBy('route_code')->orderBy('route_leg')->get();
                    $path = $exportSvc->exportFlights($data);
                    break;
                case ImportExportType::SUBFLEETS:
                    $data = Subfleet::all();
                    $path = $exportSvc->exportSubfleets($data);
                    break;
            }

            if (!isset($path)) {
                $this->failure();

                return null;
            }

            $this->sendSuccessNotification();

            return response()->download($path, $file_name, ['content-type' => 'text/csv'])->deleteFileAfterSend(true);
        });

        $this->successNotificationTitle('Data exported successfully');

        $this->icon('heroicon-o-document-arrow-down');

        $this->groupedIcon('heroicon-m-document-arrow-down');
    }
}
