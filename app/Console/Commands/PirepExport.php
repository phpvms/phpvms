<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

class PirepExport extends Command
{
    protected $signature = 'phpvms:pirep-export {id}';

    protected $description = 'PIREP table export';

    /**
     * Run dev related commands
     */
    public function handle(): void
    {
        $pirep_id = $this->argument('id');
        if (empty($pirep_id)) {
            $this->error('No PIREP ID specified');
            exit;
        }

        // List the tables to export and the column name for the pirep id
        $tables = [
            'pireps'               => 'id',
            'acars'                => 'pirep_id',
            'pirep_comments'       => 'pirep_id',
            'pirep_fares'          => 'pirep_id',
            'pirep_field_values'   => 'pirep_id',
            'expenses'             => 'ref_model_id',
            'journal_transactions' => 'ref_model_id',
        ];

        $export_tables = [];
        foreach ($tables as $table => $key) {
            $export_tables[$table] = [];

            $rows = DB::table($table)
                ->where($key, '=', $pirep_id)
                ->get();

            foreach ($rows as $row) {
                $export_tables[$table][] = (array) $row;
            }
        }

        $yaml = Yaml::dump($export_tables, 4, 2);
        echo $yaml;
    }
}
