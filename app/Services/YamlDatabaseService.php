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

    protected array $timeColumns = [
        'arrival_time',
        'departure_time',
    ];

    protected array $dateColumns = [
        'post_date',
    ];

    protected array $datetimeColumns = [
        'block_off_time',
        'block_on_time',
        'landing_time',
    ];

    protected function time(): string
    {
        return (string) Carbon::now('UTC'); // ->format('Y-m-d H:i:s');
    }

    /**
     * @throws Exception
     */
    public function seedFromYamlFile(string $yaml_file, bool $ignore_errors = true): array
    {
        $yml = file_get_contents($yaml_file);
        $yml = Yaml::parse($yml);

        return $this->seedFromYaml($yml, $ignore_errors);
    }

    /**
     * @throws Exception
     */
    public function seedFromYaml(mixed $yml, bool $ignore_errors = true): array
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
                        $ignore_errors,
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

            // Raw writes here bypass SettingService::store(), so drop the
            // per-request memo to keep same-request reads coherent.
            if ($table === 'settings') {
                app(SettingService::class)->clearMemo();
            }
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

        // Normalize date/time fields, narrowing each value to its column's real
        // type. "now" resolves to the current UTC instant; everything else is
        // parsed. Passing a full datetime into a PostgreSQL `time` or `date`
        // column otherwise raises an "invalid input syntax" error.
        foreach ($row as $column => $value) {
            if (empty($value)) {
                continue;
            }

            $format = $this->dateTimeFormatFor((string) $column);
            if ($format === null) {
                continue;
            }

            $carbon = strtolower((string) $value) === 'now'
                ? Carbon::now('UTC')
                : Carbon::parse($value);

            $row[$column] = $carbon->format($format);
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

    /**
     * Resolve the storage format for a date/time column, or null when the
     * column is not a date/time field.
     */
    private function dateTimeFormatFor(string $column): ?string
    {
        return match (true) {
            in_array($column, $this->timeColumns, true) => 'H:i:s',
            in_array($column, $this->dateColumns, true) => 'Y-m-d',
            str_ends_with($column, '_at'),
            in_array($column, $this->datetimeColumns, true) => 'Y-m-d H:i:s',
            default                                         => null,
        };
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

        // The third `is_called` argument is false for an empty table so the
        // next id starts at 1 instead of skipping to 2. For a populated table
        // it is true, so the next id is MAX + 1.
        DB::statement(sprintf(
            "SELECT setval('%s', COALESCE(MAX(%s), 1), MAX(%s) IS NOT NULL) FROM %s",
            $sequence,
            $idColumn,
            $idColumn,
            $fullTable
        ));
    }
}
