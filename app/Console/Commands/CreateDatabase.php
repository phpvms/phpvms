<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

use function Laravel\Prompts\confirm;

#[AsCommand(name: 'db:create', description: 'Create a new database based on your configuration')]
#[Signature('db:create 
                            {--reset : Drop the database before creating it} 
                            {--force : Force the operation to run without prompts} 
                            {--connection= : The database connection to use}')]
class CreateDatabase extends Command
{
    public function __construct(private readonly Filesystem $file)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(): int
    {
        if ($this->option('reset') && !$this->option('force')) {
            $confirmed = confirm(
                label: 'The "reset" option will completely destroy the existing database. Are you sure?',
                default: false,
                hint: 'This action cannot be undone.'
            );

            if (!$confirmed) {
                $this->warn('Database creation cancelled.');

                return self::SUCCESS;
            }
        }

        $connection = $this->option('connection') ?: config('database.default');
        $this->components->info(sprintf('Using connection: [%s]', $connection));

        $driver = config(sprintf('database.connections.%s.driver', $connection));

        return match ($driver) {
            'mysql', 'mariadb'  => $this->createMysqlOrMariaDb($connection),
            'sqlite'            => $this->createSqlite($connection),
            'pgsql', 'postgres' => $this->createPostgres($connection),
            default             => $this->fail(sprintf('Unsupported database driver: [%s]', $driver)),
        };
    }

    /**
     * Create the MySQL or MariaDB database safely.
     *
     * @throws Throwable
     */
    protected function createMysqlOrMariaDb(string $connection): int
    {
        $config = config('database.connections.'.$connection);
        $databaseName = $config['database'];
        $charset = $config['charset'] ?? 'utf8mb4';
        $collation = $config['collation'] ?? 'utf8mb4_unicode_ci';

        // Temporarily clear the database name so PDO can connect to the server root
        // If we don't do this, PDO will throw an exception trying to connect to a DB that doesn't exist yet.
        config([sprintf('database.connections.%s.database', $connection) => null]);

        try {
            $pdo = DB::connection($connection)->getPdo();

            if ($this->option('reset')) {
                $this->components->task('Dropping database '.$databaseName, function () use ($pdo, $databaseName): void {
                    $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
                });
            }

            $this->components->task('Creating database '.$databaseName, function () use ($pdo, $databaseName, $charset, $collation): void {
                $pdo->exec(sprintf(
                    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
                    $databaseName,
                    $charset,
                    $collation
                ));
            });

            return self::SUCCESS;

        } catch (PDOException $pdoException) {
            Log::error('Failed to create database: '.$pdoException->getMessage());
            $this->fail($pdoException->getMessage());
        } finally {
            // Always restore the config and purge the temporary root connection
            config([sprintf('database.connections.%s.database', $connection) => $databaseName]);
            DB::purge($connection);
        }
    }

    /**
     * Create the SQLite database natively without external OS binaries.
     */
    protected function createSqlite(string $connection): int
    {
        $dbPath = config(sprintf('database.connections.%s.database', $connection));

        if ($dbPath === ':memory:') {
            $this->components->info('In-memory database selected, skipping file creation.');

            return self::SUCCESS;
        }

        if ($this->option('reset') && $this->file->exists($dbPath)) {
            $this->components->task('Deleting existing SQLite database file', function () use ($dbPath): void {
                $this->file->delete($dbPath);
            });
        }

        if (!$this->file->exists($dbPath)) {
            $this->components->task('Creating SQLite database file at '.$dbPath, function () use ($dbPath): void {
                // Creates an empty file natively. No sqlite3 binaries or OS detection needed!
                $this->file->ensureDirectoryExists(dirname((string) $dbPath));
                $this->file->put($dbPath, '');
            });
        } else {
            $this->components->info('SQLite database file already exists.');
        }

        return self::SUCCESS;
    }

    /**
     * Create the PostgreSQL database.
     *
     * @throws Throwable
     */
    protected function createPostgres(string $connection): int
    {
        $this->fail('PostgreSQL support is not implemented yet!');
    }
}
