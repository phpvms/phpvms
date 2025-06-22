<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Services\Installer\ConfigService;
use App\Services\Installer\MigrationService;
use Illuminate\Support\Facades\App;

/**
 * Create the config files
 */
class CreateConfigs extends Command
{
    protected $signature = 'phpvms:config {db_host} {db_name} {db_user} {db_pass}';

    protected $description = 'Create the config files';

    public function __construct(
        private readonly MigrationService $migrationSvc,
    ) {
        parent::__construct();
    }

    /**
     * Run dev related commands
     *
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    public function handle(): void
    {
        $this->writeConfigs();

        // Reload the configuration
        App::boot();

        $this->migrationSvc->runAllMigrations();

        $this->info('Done!');
    }

    /**
     * Rewrite the configuration files
     *
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    protected function writeConfigs()
    {
        /** @var ConfigService $cfgSvc */
        $cfgSvc = app(ConfigService::class);

        $this->info('Removing the old config files');

        // Remove the old files
        $config_file = base_path('config.php');
        if (file_exists($config_file)) {
            unlink($config_file);
        }

        $env_file = base_path('.env');
        if (file_exists($env_file)) {
            unlink($env_file);
        }

        // {name} {db_host} {db_name} {db_user} {db_pass}

        $this->info('Regenerating the config files');
        $cfgSvc->createConfigFiles([
            'SITE_NAME'     => $this->argument('name'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'       => $this->argument('db_host'),
            'DB_DATABASE'   => $this->argument('db_name'),
            'DB_USERNAME'   => $this->argument('db_user'),
            'DB_PASSWORD'   => $this->argument('db_pass'),
        ]);

        $this->info('Config files generated!');
    }
}
