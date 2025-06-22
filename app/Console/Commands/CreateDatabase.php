<?php

namespace App\Console\Commands;

use App\Console\Services\Database;
use App\Contracts\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tivie\OS\Detector;

class CreateDatabase extends Command
{
    protected $signature = 'database:create {--reset} {--migrate} {--conn=?}';

    protected $description = 'Create a database';

    protected Detector $os;

    /**
     * CreateDatabase constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->os = new Detector();
    }

    /**
     * Create the mysql database
     */
    protected function create_mysql_or_mariadb(string $dbkey): ?bool
    {
        $host = config($dbkey.'host');
        $port = config($dbkey.'port');
        $name = config($dbkey.'database');
        config($dbkey.'username');
        config($dbkey.'password');

        $dbSvc = new Database();
        $dsn = $dbSvc->createDsn($host, $port);
        Log::info('Connection string: '.$dsn);

        try {
            $conn = DB::connection(config('database.default'))->getPdo();
        } catch (\PDOException $e) {
            Log::error($e);

            return false;
        }

        if ($this->option('reset') === true) {
            $sql = "DROP DATABASE IF EXISTS `$name`";

            try {
                Log::info('Dropping database: '.$sql);
                $conn->exec($sql);
            } catch (\PDOException $e) {
                Log::error($e);
            }
        }

        $sql = "CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET UTF8MB4 COLLATE utf8mb4_unicode_ci";

        try {
            Log::info('Creating database: '.$sql);
            $conn->exec($sql);
        } catch (\PDOException $e) {
            Log::error($e);

            return false;
        }

        return null;
    }

    /**
     * Create the sqlite database
     */
    protected function create_sqlite(string $dbkey)
    {
        $dbPath = config($dbkey.'database');

        // Skip if running in memory
        if ($dbPath === ':memory:') {
            return;
        }

        $exec = 'sqlite3';
        if ($this->os->isWindowsLike()) {
            $exec = 'sqlite3.exe';
        }

        if ($this->option('reset') === true && file_exists($dbPath)) {
            unlink(config($dbkey.'database'));
        }

        if (!file_exists($dbPath)) {
            $cmd = [
                $exec,
                $dbPath,
                '".exit"',
            ];

            $this->runCommand($cmd);
        }
    }

    protected function create_postgres($dbkey)
    {
        $this->error('Not supported yet!');
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /*if ($this->option('reset')) {
            if(!$this->confirm('The "reset" option will destroy the database, are you sure?')) {
                return false;
            }
        }*/

        $this->info('Using connection "'.config('database.default').'"');

        $conn = config('database.default');
        $dbkey = 'database.connections.'.$conn.'.';

        if (config($dbkey.'driver') === 'mysql' || config($dbkey.'driver') === 'mariadb') {
            $this->create_mysql_or_mariadb($dbkey);
        } elseif (config($dbkey.'driver') === 'sqlite') {
            $this->create_sqlite($dbkey);
        } // TODO: Eventually
        elseif (config($dbkey.'driver') === 'postgres') {
            $this->create_postgres($dbkey);
        }
    }
}
