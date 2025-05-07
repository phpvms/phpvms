<?php

namespace App\Services;

use App\Contracts\Service;
use App\Support\Database;
use Carbon\Carbon;
use Webpatser\Uuid\Uuid;

class DatabaseService extends Service
{
    protected $time_fields = [
        'created_at',
        'updated_at',
    ];

    protected $uuid_tables = [
        'acars',
        'flights',
        'pireps',
    ];

    protected function time(): string
    {
        return Carbon::now('UTC'); // ->format('Y-m-d H:i:s');
    }

    /**
     * @param bool $ignore_errors
     *
     * @throws \Exception
     */
    public function seed_from_yaml_file($yaml_file, $ignore_errors = false): array
    {
        return Database::seed_from_yaml_file($yaml_file, $ignore_errors);
    }

    /**
     * @param bool $ignore_errors
     *
     * @throws \Exception
     */
    public function seed_from_yaml($yml, $ignore_errors = false): array
    {
        return Database::seed_from_yaml($yml, $ignore_errors);
    }

    /**
     * @return mixed
     *
     * @throws \Exception
     */
    public function insert_row($table, $row)
    {
        // see if this table uses a UUID as the PK
        // if no ID is specified
        /* @noinspection NestedPositiveIfStatementsInspection */
        if (\in_array($table, $this->uuid_tables, true) && !array_key_exists('id', $row)) {
            $row['id'] = Uuid::generate()->string;
        }

        return Database::insert_row($table, $row);
    }
}
