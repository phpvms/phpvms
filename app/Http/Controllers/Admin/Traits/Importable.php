<?php

namespace App\Http\Controllers\Admin\Traits;

use App\Http\Requests\ImportRequest;
use App\Models\Enums\ImportExportType;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

trait Importable
{
    /**
     * Import a file, passing in the import/export type
     *
     * @param Request $request    Request object
     * @param int     $importType Refer to \App\Models\Enums\ImportExportType
     *
     * @throws \Illuminate\Validation\ValidationException
     *
     * @return mixed
     */
    public function importFile(Request $request, int $importType): mixed
    {
        ImportRequest::validate($request);
        $path = Storage::putFileAs(
            'import',
            $request->file('csv_file'),
            'import_'.ImportExportType::label($importType).'.csv'
        );

        /** @var ImportService */
        $importSvc = app(ImportService::class);

        $path = storage_path('app/'.$path);
        Log::info('Uploaded airport import file to '.$path);

        $delete_previous = get_truth_state($request->get('delete'));

        if ($importType === ImportExportType::FLIGHTS) {
            $delete_previous = $request->get('delete');
        }

        switch ($importType) {
            case ImportExportType::AIRCRAFT:
                return $importSvc->importAircraft($path, $delete_previous);
            case ImportExportType::AIRPORT:
                return $importSvc->importAirports($path, $delete_previous);
            case ImportExportType::EXPENSES:
                return $importSvc->importExpenses($path, $delete_previous);
            case ImportExportType::FARES:
                return $importSvc->importFares($path, $delete_previous);
            case ImportExportType::FLIGHTS:
                return $importSvc->importFlights($path, $delete_previous);
            case ImportExportType::SUBFLEETS:
                return $importSvc->importSubfleets($path, $delete_previous);
        }

        throw new InvalidArgumentException('Unknown import type!');
    }
}
