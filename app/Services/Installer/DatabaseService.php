<?php

namespace App\Services\Installer;

use App\Contracts\Service;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use PDO;

class DatabaseService extends Service
{
    /**
     * Check the PHP version that it meets the minimum requirement.
     *
     *
     * @return bool
     */
    public function checkDbConnection($driver, $host, $port, $name, $user, $pass)
    {
        Log::info('Testing Connection: '.$driver.'::'.$user.':<hidden>@'.$host.':'.$port.';'.$name);

        // TODO: Needs testing
        if ($driver === 'postgres') {
            $dsn = "pgsql:host=$host;port=$port;dbname=$name";

            try {
                $conn = new PDO($dsn, $user, $pass);
            } catch (\PDOException $e) {
                throw $e;
            }

            return true;
        }

        // Default MySQL
        $dsn = "mysql:host=$host;port=$port;dbname=$name";
        Log::info('Connection string: '.$dsn);

        try {
            $conn = new PDO($dsn, $user, $pass);
        } catch (\PDOException $e) {
            throw $e;
        }

        return true;
    }

    /**
     * Setup the database by running the migration commands
     * Only run the setup for sqlite, otherwise, we're assuming
     * that the MySQL database has already been created.
     */
    public function setupDB()
    {
        $output = '';

        if (config('database.default') === 'sqlite') {
            Artisan::call('database:create');
            $output .= Artisan::output();
        }

        return trim($output);
    }
}
