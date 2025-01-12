<?php

namespace App\Services;

use App\Contracts\ImportExport;
use App\Contracts\Service;
use App\Models\Airport;
use App\Models\Expense;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\FlightFieldValue;
use App\Repositories\FlightRepository;
use App\Services\ImportExport\AircraftImporter;
use App\Services\ImportExport\AirportImporter;
use App\Services\ImportExport\ExpenseImporter;
use App\Services\ImportExport\FareImporter;
use App\Services\ImportExport\FlightImporter;
use App\Services\ImportExport\SubfleetImporter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use League\Csv\Exception;
use League\Csv\Reader;

class ImportService extends Service
{
    /**
     * ImporterService constructor.
     */
    public function __construct(
        private readonly FlightRepository $flightRepo
    ) {
    }

    /**
     * Throw a validation error back up because it will automatically show
     * itself under the CSV file upload, and nothing special needs to be done.
     *
     *
     * @throws ValidationException
     */
    protected function throwError($error, ?\Exception $e = null): void
    {
        Log::error($error);
        if ($e) {
            Log::error($e->getMessage());
        }

        $validator = Validator::make([], []);
        $validator->errors()->add('csv_file', $error);

        throw new ValidationException($validator);
    }

    /**
     * @throws ValidationException
     *
     * @return Reader
     */
    public function openCsv($csv_file)
    {
        try {
            $reader = Reader::createFromPath($csv_file, 'r');
            $reader->setDelimiter(',');
            $reader->setHeaderOffset(0);
            $reader->setEnclosure('"');
            $reader->setEscape('\\');

            return $reader;
        } catch (Exception $e) {
            $this->throwError('Error opening CSV: '.$e->getMessage(), $e);
        }
    }

    /**
     * Run the actual importer, pass in one of the Import classes which implements
     * the ImportExport interface.
     *
     *
     * @throws ValidationException
     */
    protected function runImport($file_path, ImportExport $importer): array
    {
        $reader = $this->openCsv($file_path);

        $cols = array_keys($importer->getColumns());
        $first_header = $cols[0];

        $first = true;
        $header_rows = $reader->getHeader();
        $records = $reader->getRecords($header_rows);
        foreach ($records as $offset => $row) {
            // turn it into a collection and run some filtering
            $row = collect($row)->map(function ($val, $index) {
                $val = trim($val);

                return str_ireplace(['\\n', '\\r'], '', $val);
            })->toArray();

            // Try to validate
            $validator = Validator::make($row, $importer->getColumns());
            if ($validator->fails()) {
                $errors = 'Error in row '.$offset.','.implode(';', $validator->errors()->all());
                $importer->errorLog($errors);

                continue;
            }

            $importer->import($row, $offset);
        }

        return $importer->status;
    }

    /**
     * Import aircraft.
     *
     * @param string $csv_file
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function importAircraft($csv_file, bool $delete_previous = true)
    {
        if ($delete_previous) {
            // TODO: delete airports
        }

        $importer = new AircraftImporter();

        return $this->runImport($csv_file, $importer);
    }

    /**
     * Import airports.
     *
     * @param string $csv_file
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function importAirports($csv_file, bool $delete_previous = true)
    {
        if ($delete_previous) {
            Airport::truncate();
        }

        $importer = new AirportImporter();

        return $this->runImport($csv_file, $importer);
    }

    /**
     * Import expenses.
     *
     * @param string $csv_file
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function importExpenses($csv_file, bool $delete_previous = true)
    {
        if ($delete_previous) {
            Expense::truncate();
        }

        $importer = new ExpenseImporter();

        return $this->runImport($csv_file, $importer);
    }

    /**
     * Import fares.
     *
     * @param string $csv_file
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function importFares($csv_file, bool $delete_previous = true)
    {
        if ($delete_previous) {
            Fare::truncate();
        }

        $importer = new FareImporter();

        return $this->runImport($csv_file, $importer);
    }

    /**
     * Import flights.
     *
     * @param string $csv_file
     * @param bool   $delete_previous
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function importFlights($csv_file, ?string $delete_previous = null)
    {
        if (!empty($delete_previous)) {
            // If delete_previous contains all, then delete everything
            if ($delete_previous === 'all') {
                Flight::truncate();
                FlightFieldValue::truncate();
            } elseif ($delete_previous === 'core') {
                // Delete all flights where the owner_type is null
                Flight::whereNull('owner_type')->delete();
            }
        }

        $importer = new FlightImporter();

        return $this->runImport($csv_file, $importer);
    }

    /**
     * Import subfleets.
     *
     * @param string $csv_file
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function importSubfleets($csv_file, bool $delete_previous = true)
    {
        if ($delete_previous) {
            // TODO: Cleanup subfleet data
        }

        $importer = new SubfleetImporter();

        return $this->runImport($csv_file, $importer);
    }
}
