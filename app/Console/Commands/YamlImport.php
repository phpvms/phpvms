<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Services\DatabaseService;
use Exception;

/**
 * Class YamlImport
 */
class YamlImport extends Command
{
    protected $signature = 'phpvms:yaml-import {files*}';

    protected $description = 'Developer commands';

    protected DatabaseService $dbSvc;

    /**
     * YamlImport constructor.
     */
    public function __construct(DatabaseService $dbSvc)
    {
        parent::__construct();

        $this->dbSvc = $dbSvc;
    }

    /**
     * Run dev related commands
     *
     * @throws Exception
     */
    public function handle()
    {
        $files = $this->argument('files');
        if (empty($files)) {
            $this->error('No files to import specified!');
            exit;
        }

        $ignore_errors = true;

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->error('File '.$file.' doesn\'t exist');
                exit;
            }

            $this->info('Importing '.$file);

            $imported = $this->dbSvc->seed_from_yaml_file($file, $ignore_errors);
            foreach ($imported as $table => $count) {
                $this->info('Imported '.$count.' records from "'.$table.'"');
            }
        }
    }
}
