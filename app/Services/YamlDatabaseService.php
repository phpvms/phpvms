<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class YamlDatabaseService extends Service
{
    protected array $uuidTables = [
        'acars',
        'flights',
        'pireps',
    ];

    protected array $datetimeTimeColumns = [
        'arrival_time',
        'block_off_time',
        'block_on_time',
        'departure_time',
        'landing_time',
        'post_date',
    ];

    protected function time(): string
    {
        return (string) Carbon::now('UTC'); // ->format('Y-m-d H:i:s');
    }

    /**
     * @throws Exception
     */
    public function seedFromYamlFile(string $yaml_file, bool $ignore_errors = false): array
    {
        $yml = file_get_contents($yaml_file);
        $yml = Yaml::parse($yml);

        return $this->seedFromYaml($yml, $ignore_errors);
    }

    /**
     * @throws Exception
     */
    public function seedFromYaml(mixed $yml, bool $ignore_errors = false): array
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
                    $this->insertRow(
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

            $this->resetPostgresSequence($table, $id_column);
        }

        return $imported;
    }

    /**
     * @throws Exception
     */
    public function insertRow(
        string $table,
        array $row = [],
        string $id_col = 'id',
        array $ignore_on_updates = [],
        bool $ignore_errors = true,
        bool $ignore_if_exists = true,
    ): array {
        if ($row === []) {
            return $row;
        }

        if (!array_key_exists('id', $row) && \in_array($table, $this->uuidTables, true)) {
            $row['id'] = Str::uuid();
        }

        // encrypt any password fields
        if (array_key_exists('password', $row)) {
            $row['password'] = bcrypt($row['password']);
        }

        // if any time fields are == to "now", then insert the right time
        foreach ($row as $column => $value) {
            if (empty($value)) {
                continue;
            }

            $isDateTimeColumn = str_ends_with((string) $column, '_at')
                || in_array($column, $this->datetimeTimeColumns, true);
            if (!$isDateTimeColumn) {
                continue;
            }

            if (strtolower((string) $value) === 'now') {
                $row[$column] = Carbon::now('UTC')->toDateTimeString();
            } else {
                $row[$column] = Carbon::parse($value)->toDateTimeString();
            }
        }

        $count = 0;
        if (array_key_exists($id_col, $row)) {
            $count = DB::table($table)->where($id_col, $row[$id_col])->count($id_col);
        }

        if ($count > 0 && $ignore_if_exists) {
            return $row;
        }

        if ($count > 0) {
            foreach ($ignore_on_updates as $ignore_column) {
                if (array_key_exists($ignore_column, $row)) {
                    unset($row[$ignore_column]);
                }
            }
        }

        try {
            // Run the write inside a (possibly nested) transaction so a failure
            // rolls back to a SAVEPOINT instead of poisoning the surrounding
            // transaction. On PostgreSQL any failed statement aborts the whole
            // transaction, so swallowing the exception without a savepoint would
            // leave every later query failing with "current transaction is aborted".
            DB::transaction(function () use ($count, $id_col, $table, $row): void {
                if ($count > 0) {
                    DB::table($table)
                        ->where($id_col, $row[$id_col])
                        ->update($row);
                } else {
                    DB::table($table)->insert($row);
                }
            });
        } catch (QueryException $queryException) {
            Log::error('Error while running query: '.$queryException->getMessage(), ['exception' => $queryException]);
            if (!$ignore_errors) {
                throw $queryException;
            }
        }

        return $row;
    }

    protected function resetPostgresSequence(string $table, string $idColumn = 'id'): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $fullTable = DB::getTablePrefix().$table;

        // Guard every step with a query that cannot fail, because on PostgreSQL
        // a failed statement aborts the entire surrounding transaction. The old
        // implementation relied on catching QueryException, but by then the
        // transaction was already poisoned and every later query in the same
        // test transaction failed with "current transaction is aborted".
        if (!Schema::hasColumn($table, $idColumn)) {
            return;
        }

        // Returns null for pivot tables and uuid/string primary keys that have
        // no owned sequence, so we skip setval entirely instead of targeting a
        // non-existent "<table>_<id>_seq" relation.
        $sequence = DB::scalar('SELECT pg_get_serial_sequence(?, ?)', [$fullTable, $idColumn]);

        if ($sequence === null) {
            return;
        }

        DB::statement(sprintf("SELECT setval('%s', COALESCE((SELECT MAX(%s) FROM %s), 1))", $sequence, $idColumn, $fullTable));
    }
}
