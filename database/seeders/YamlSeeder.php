<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

use function in_array;

class YamlSeeder extends Seeder
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

    /**
     * Run the database seeds.
     *
     * @throws Exception
     */
    public function run(): void
    {
        $this->seedFromYamlFile(database_path('seeders/base/base.yml'));

        $env = App::environment();
        $seedPath = database_path('seeders/'.$env);
        if (!File::isDirectory($seedPath)) {
            return;
        }

        Log::info('current environment '.$env);

        collect(File::allFiles($seedPath))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'yml')
            ->each(function (SplFileInfo $file): void {
                $path = $file->getPathname();
                Log::info('reading '.$path);
                $this->seedFromYamlFile($path);
            });
    }

    /**
     * @throws Exception
     */
    public function seedFromYamlFile(string $yaml_file, bool $ignore_errors = false): array
    {
        $yml = file_get_contents($yaml_file);
        if ($yml === false) {
            throw new \RuntimeException('Unable to read YAML seed file: '.$yaml_file);
        }

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
            // set the number imported to zero
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

        if (!array_key_exists('id', $row) && in_array($table, $this->uuidTables, true)) {
            $row['id'] = Str::uuid();
        }

        // encrypt any password fields
        if (array_key_exists('password', $row)) {
            $row['password'] = bcrypt($row['password']);
        }

        // Convert datetime fields. Only process columns whose names indicate
        // they hold timestamps (ending in _at, or specific _time columns
        // that are actual datetime types, not integer durations).
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
                DB::table($table)->insert($row);
            }
        } catch (QueryException $queryException) {
            Log::error('Error while running query: '.$queryException->getMessage(), ['exception' => $queryException]);
            if (!$ignore_errors) {
                throw $queryException;
            }
        }

        return $row;
    }

    /**
     * Reset a PostgreSQL sequence after inserting rows with explicit IDs.
     * Raw DB::table()->insert() with explicit IDs bypasses auto-increment
     * sequences, so subsequent Eloquent creates would collide on the PK.
     */
    protected function resetPostgresSequence(string $table, string $idColumn = 'id'): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            $fullTable = DB::getTablePrefix().$table;
            $sequence = $fullTable.'_'.$idColumn.'_seq';
            DB::statement(sprintf("SELECT setval('%s', COALESCE((SELECT MAX(%s) FROM %s), 1))", $sequence, $idColumn, $fullTable));
        } catch (QueryException) {
            // Table may not have a serial column; ignore.
        }
    }
}
