<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

class Database
{
    protected static function time(): string
    {
        return Carbon::now('UTC');
    }

    /**
     * @throws \Exception
     */
    public static function seed_from_yaml_file($yaml_file, bool $ignore_errors = false): array
    {
        $yml = file_get_contents($yaml_file);
        $yml = Yaml::parse($yml);

        return static::seed_from_yaml($yml, $ignore_errors);
    }

    public static function seed_from_yaml(mixed $yml, bool $ignore_errors = false): array
    {
        $imported = [];

        if (empty($yml)) {
            return $imported;
        }

        foreach ($yml as $table => $data) {
            $imported[$table] = 0;

            $id_column = 'id';
            if (array_key_exists('id_column', $data)) {
                $id_column = $data['id_column'];
            }

            $ignore_on_update = [];
            if (array_key_exists('ignore_on_update', $data)) {
                $ignore_on_update = $data['ignore_on_update'];
            }

            $ignore_if_exists = false;
            if (array_key_exists('ignore_if_exists', $data)) {
                $ignore_if_exists = $data['ignore_if_exists'];
            }

            $rows = array_key_exists('data', $data) ? $data['data'] : $data;

            foreach ($rows as $row) {
                try {
                    static::insert_row(
                        $table,
                        $row,
                        $id_column,
                        $ignore_on_update,
                        true,
                        $ignore_if_exists
                    );
                } catch (QueryException $e) {
                    if ($ignore_errors) {
                        continue;
                    }

                    throw $e;
                }

                $imported[$table]++;
            }
        }

        return $imported;
    }

    /**
     * @param  string $id_col The ID column to use for update/insert
     * @return mixed
     */
    public static function insert_row(
        string $table,
        array $row = [],
        string $id_col = 'id',
        array $ignore_on_updates = [],
        bool $ignore_errors = true,
        bool $ignore_if_exists = true,
    ) {
        // encrypt any password fields
        if (array_key_exists('password', $row)) {
            $row['password'] = bcrypt($row['password']);
        }

        if ($row === []) {
            return $row;
        }

        // if any time fields are == to "now", then insert the right time
        foreach ($row as $column => $value) {
            if (!empty($value) && strtolower((string) $value) === 'now') {
                $row[$column] = static::time();
            }
        }

        $count = 0;
        if (array_key_exists($id_col, $row)) {
            $count = DB::table($table)->where($id_col, $row[$id_col])->count($id_col);
        }

        try {
            if ($count > 0) {
                if ($ignore_if_exists) {
                    return $row;
                }

                foreach ($ignore_on_updates as $ignore_column) {
                    if (array_key_exists($ignore_column, $row)) {
                        unset($row[$ignore_column]);
                    }
                }

                DB::table($table)
                    ->where($id_col, $row[$id_col])
                    ->update($row);
            } else {
                // Remove ID column if it exists and its empty, let the DB set it
                /*if (array_key_exists($id_col, $row) && empty($row[$id_col])) {
                    unset($row[$id_col]);
                }*/

                DB::table($table)->insert($row);
            }
        } catch (QueryException $e) {
            Log::error($e);
            throw_unless($ignore_errors, $e);
        }

        return $row;
    }
}
