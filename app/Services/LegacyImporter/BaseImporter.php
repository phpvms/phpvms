<?php

namespace App\Services\LegacyImporter;

use App\Services\Installer\LoggerTrait;
use App\Services\LegacyImporterService;
use App\Utils\IdMapper;
use App\Utils\ImporterDB;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

abstract class BaseImporter
{
    use Dispatchable;
    use InteractsWithQueue;
    use LoggerTrait;
    use Queueable;

    /**
     * Holds the connection to the legacy database
     *
     * @var ImporterDB
     */
    protected $db;

    /**
     * The mapper class used for old IDs to new IDs
     *
     * @var IdMapper
     */
    protected $idMapper;

    /**
     * The legacy table this importer targets
     *
     * @var string
     */
    protected $table;

    /**
     * The column used for the ID, used for the ORDER BY
     *
     * @var string
     */
    protected $idField = 'id';

    public function __construct()
    {
        $importerService = app(LegacyImporterService::class);
        $this->db = new ImporterDB($importerService->getCredentials());
        $this->idMapper = app(IdMapper::class);
    }

    /**
     * The start method. Takes the offset to start from
     *
     * @param int $start
     *
     * @return mixed
     */
    abstract public function run($start = 0);

    /**
     * Return a manifest of the import tasks to run. Returns an array of objects,
     * which contain a start and end row
     */
    public function getManifest(): array
    {
        $manifest = [];

        // Ensure that the table exists; if it doesn't skip it from the manifest
        if (!$this->db->tableExists($this->table)) {
            Log::info('Table '.$this->table.' doesn\'t exist');

            return [];
        }

        $start = 0;
        $total_rows = $this->db->getTotalRows($this->table);
        Log::info('Found '.$total_rows.' rows for '.$this->table);

        do {
            $end = $start + $this->db->batchSize;
            if ($end > $total_rows) {
                $end = $total_rows;
            }

            $idx = $start + 1;

            $manifest[] = [
                'importer' => static::class,
                'start'    => $start,
                'end'      => $end,
                'message'  => 'Importing '.$this->table.' ('.$idx.' - '.$end.' of '.$total_rows.')',
            ];

            $start += $this->db->batchSize;
        } while ($start < $total_rows);

        return $manifest;
    }

    /**
     * Determine what columns exist, can be used for feature testing between v2/v5
     */
    public function getColumns(): array
    {
        return [];
    }

    /**
     * @param mixed $date
     *
     * @return Carbon
     */
    protected function parseDate($date)
    {
        return Carbon::parse($date);
    }

    /**
     * Take a decimal duration and convert it to minutes
     *
     *
     * @param mixed $duration
     *
     * @return float|int
     */
    protected function convertDuration($duration)
    {
        if (strpos($duration, '.') !== false) {
            $delim = '.';
        } elseif (strpos($duration, ':')) {
            $delim = ':';
        } else {
            // no delimiter, assume it's just a straight hour
            return (int) $duration * 60;
        }

        $hm = explode($delim, $duration);
        $hours = (int) $hm[0] * 60;
        $mins = (int) $hm[1];

        return $hours + $mins;
    }
}
