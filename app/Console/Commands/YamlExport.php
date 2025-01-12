<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

/**
 * Class YamlExport
 */
class YamlExport extends Command
{
    protected $signature = 'phpvms:yaml-export {tables*}';

    protected $description = 'YAML table export';

    /**
     * Run dev related commands
     */
    public function handle()
    {
        $tables = $this->argument('tables');
        if (empty($tables)) {
            $this->error('No tables specified');
            exit;
        }

        // A "preset" for exporting the base set of data
        if ($tables[0] === 'base') {
            $tables = [
                'airlines',
                'aircraft',
                'subfleets',
                'subfleet_fare',
                'subfleet_rank',
                'bids',
                'fares',
                'flights',
            ];
        }

        $export_tables = [];
        foreach ($tables as $table) {
            $export_tables[$table] = [];

            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $export_tables[$table][] = (array) $row;
            }
        }

        $yaml = Yaml::dump($export_tables, 4, 2);
        echo $yaml;
    }
}
