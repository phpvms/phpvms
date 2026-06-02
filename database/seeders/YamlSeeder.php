<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\YamlDatabaseService;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\SplFileInfo;

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

    public function __construct(
        private readonly YamlDatabaseService $databaseSvc
    ) {}

    /**
     * Run the database seeds.
     *
     * @throws Exception
     */
    public function run(): void
    {
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
                $this->databaseSvc->seedFromYamlFile($path);
            });
    }
}
