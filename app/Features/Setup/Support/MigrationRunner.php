<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationSkipped;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Runs a migrator's pending migrations, reporting each one to the step context.
 */
final readonly class MigrationRunner
{
    public function __construct(
        private Dispatcher $events,
        private MigrationPaths $paths,
    ) {}

    /**
     * @param list<string> $paths
     */
    public function run(Migrator $migrator, array $paths, StepContext $ctx): void
    {
        $repository = $migrator->getRepository();

        if (!$repository->repositoryExists()) {
            $repository->createRepository();
        }

        $total = count($this->paths->pending($repository, $paths));

        if ($total === 0) {
            ($ctx->log)('Nothing to migrate');

            return;
        }

        $done = 0;
        $current = null;

        $this->events->listen(MigrationStarted::class, static function (MigrationStarted $event) use (&$current): void {
            $current = $event->name;
        });
        $this->events->listen(MigrationEnded::class, static function (MigrationEnded $event) use (&$current, &$done, $total, $ctx): void {
            $current = null;
            $done++;
            ($ctx->log)('Migrated '.$event->name);
            ($ctx->progress)($done, $total, $event->name);
        });
        // A skipped migration fires no MigrationEnded, but still counts toward the total.
        $this->events->listen(MigrationSkipped::class, static function (MigrationSkipped $event) use (&$done, $total, $ctx): void {
            $done++;
            ($ctx->log)('Skipped '.$event->migrationName);
            ($ctx->progress)($done, $total, $event->migrationName);
        });

        try {
            $migrator->run($paths);
        } catch (QueryException $queryException) {
            if ($current !== null && $this->tableExists($queryException)) {
                throw new RuntimeException(
                    "Migration {$current} failed: a table it creates already exists. Restore the database from the backup before retrying.",
                    0,
                    $queryException,
                );
            }

            throw $queryException;
        } finally {
            $this->events->forget(MigrationStarted::class);
            $this->events->forget(MigrationEnded::class);
            $this->events->forget(MigrationSkipped::class);
        }
    }

    private function tableExists(QueryException $e): bool
    {
        return match (DB::connection($e->getConnectionName())->getDriverName()) {
            'mysql', 'mariadb' => (int) ($e->errorInfo[1] ?? 0) === 1050,
            'pgsql'            => (string) $e->getCode() === '42P07',
            'sqlite'           => str_contains($e->getMessage(), 'already exists'),
            default            => false,
        };
    }
}
