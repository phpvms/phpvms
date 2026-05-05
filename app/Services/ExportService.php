<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ImportExport;
use App\Contracts\Service;
use App\Services\ImportExport\AircraftExporter;
use App\Services\ImportExport\AirportExporter;
use App\Services\ImportExport\ExpenseExporter;
use App\Services\ImportExport\FareExporter;
use App\Services\ImportExport\FlightExporter;
use App\Services\ImportExport\SubfleetExporter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\CannotInsertRecord;
use League\Csv\CharsetConverter;
use League\Csv\Writer;

class ExportService extends Service
{
    public function openCsv(string $path): Writer
    {
        $writer = Writer::createFromPath($path, 'w+');
        CharsetConverter::addTo($writer, 'utf-8', 'utf-8');

        return $writer;
    }

    /**
     * Run the actual importer
     *
     *
     * @throws CannotInsertRecord
     */
    protected function runExport(Collection $collection, ImportExport $exporter): string
    {
        $filename = 'export_'.$exporter->assetType.'.csv';

        // Create the directory - makes it inside of storage/app
        Storage::makeDirectory('import');
        $path = storage_path('/app/import/export_'.$filename.'.csv');

        Log::info('Exporting "'.$exporter->assetType.'" to '.$path);

        $writer = $this->openCsv($path);

        // Write out the header first
        $writer->insertOne($exporter->getColumns());

        // Write the rest of the rows
        foreach ($collection as $row) {
            $ins = $exporter->export($row);
            $writer->insertOne($ins);
        }

        return $path;
    }

    /**
     * Export all of the aircraft
     *
     *
     * @throws CannotInsertRecord
     */
    public function exportAircraft(Collection $aircraft): string
    {
        return $this->runExport($aircraft, new AircraftExporter());
    }

    /**
     * Export all of the airports
     *
     *
     * @throws CannotInsertRecord
     */
    public function exportAirports(Collection $airports): string
    {
        return $this->runExport($airports, new AirportExporter());
    }

    /**
     * Export all of the airports
     *
     *
     * @throws CannotInsertRecord
     */
    public function exportExpenses(Collection $expenses): string
    {
        return $this->runExport($expenses, new ExpenseExporter());
    }

    /**
     * Export all of the fares
     *
     *
     * @throws CannotInsertRecord
     */
    public function exportFares(Collection $fares): string
    {
        return $this->runExport($fares, new FareExporter());
    }

    /**
     * Export all of the flights
     *
     *
     * @throws CannotInsertRecord
     */
    public function exportFlights(Collection $flights): string
    {
        return $this->runExport($flights, new FlightExporter());
    }

    /**
     * Export all of the flights
     *
     *
     * @throws CannotInsertRecord
     */
    public function exportSubfleets(Collection $subfleets): string
    {
        return $this->runExport($subfleets, new SubfleetExporter());
    }
}
