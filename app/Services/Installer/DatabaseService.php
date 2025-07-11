<?php

namespace App\Services\Installer;

use App\Contracts\Service;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use PDO;

class DatabaseService extends Service
{
    /**
     * Check the PHP version that it meets the minimum requirement
     */
    public function checkDbConnection(string $driver, string $host, string $port, string $name, string $user, $pass): bool
    {
        Log::info('Testing Connection: '.$driver.'::'.$user.':<hidden>@'.$host.':'.$port.';'.$name);

        // TODO: Needs testing
        if ($driver === 'postgres') {
            $dsn = "pgsql:host=$host;port=$port;dbname=$name";

            $conn = new PDO($dsn, $user, $pass);

            return true;
        }

        // Default MySQL
        $dsn = "mysql:host=$host;port=$port;dbname=$name";
        Log::info('Connection string: '.$dsn);

        new PDO($dsn, $user, $pass);

        return true;
    }

    /**
     * Setup the database by running the migration commands
     * Only run the setup for sqlite, otherwise, we're assuming
     * that the MySQL database has already been created
     */
    public function setupDB(): string
    {
        $output = '';

        if (config('database.default') === 'sqlite') {
            Artisan::call('database:create');
            $output .= Artisan::output();
        }

        return trim($output);
    }
}
