<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Services\ImportService;

class ImportCsv extends Command
{
    protected $signature = 'phpvms:csv-import {type} {file}';

    protected $description = 'Import from a CSV file';

    /**
     * Import constructor.
     */
    public function __construct(private readonly ImportService $importer)
    {
        parent::__construct();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(): void
    {
        $type = $this->argument('type');
        $file = $this->argument('file');

        if (\in_array($type, ['flight', 'flights'], true)) {
            $status = $this->importer->importFlights($file);
        } elseif ($type === 'aircraft') {
            $status = $this->importer->importAircraft($file);
        } elseif (\in_array($type, ['airport', 'airports'], true)) {
            $status = $this->importer->importAirports($file);
        } elseif ($type === 'subfleet') {
            $status = $this->importer->importSubfleets($file);
        }

        foreach ($status['success'] as $line) {
            $this->info($line);
        }

        foreach ($status['errors'] as $line) {
            $this->error($line);
        }
    }
}
