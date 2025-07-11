<?php

namespace App\Console\Services;

use PDO;

/**
 * Class Database
 */
class Database
{
    /**
     * Create the base connection DSN, optionally include the DB name
     */
    public function createDsn($host, $port, $name = null): string
    {
        $conn = config('database.default');
        $dsn = "$conn:host=$host;port=$port";
        if (filled($name)) {
            $dsn .= ';dbname='.$name;
        }

        return $dsn;
    }

    /**
     * @throws \PDOException
     */
    public function createPDO($dsn, $user, $pass): \PDO
    {
        $conn = new PDO($dsn, $user, $pass);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        return $conn;
    }
}
