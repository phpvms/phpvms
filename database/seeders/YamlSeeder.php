<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Installer\SeederService;
use Carbon\Carbon;
use Exception;
use File;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
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

    public function __construct(
        private readonly SeederService $seederSvc
    ) {}

    /**
     * Run the database seeds.
     *
     * @throws Exception
     */
    public function run(): void
    {
        $this->seedFromYamlFile(database_path('seeders/base.yml'));

        // Special method to sync the settings
        $this->seederSvc->syncAllSettings();

        // See if there are seed files that match the environment
        $env = App::environment();
        if (!File::exists(database_path('seeders/'.$env))) {
            return;
        }

        Log::info("current environment $env");
        // Gather all of the files to seed
        collect(File::allFiles(database_path('seeders/'.$env)))
            ->each(function (SplFileInfo $file) {
                Log::info("reading $file");
            })
            ->filter(function (SplFileInfo $file): bool {
                return $file->getExtension() === 'yml';
            })
            ->each(function (string $file): void {
                $this->seedFromYamlFile($file);
            });
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

        // if any time fields are == to "now", then insert the right time
        foreach ($row as $column => $value) {
            if (!empty($value) && strtolower((string) $value) === 'now') {
                $row[$column] = Carbon::now('UTC');
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
        } catch (QueryException $queryException) {
            Log::error('Error while running query: '.$queryException->getMessage(), ['exception' => $queryException]);
            if (!$ignore_errors) {
                throw $queryException;
            }
        }

        return $row;
    }
}
