<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Addons\AddonRegistry;
use App\Features\Addons\Support\AddonRoots;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Where the schema and data migrations live, and which of them have not run.
 */
class MigrationPaths
{
    /**
     * @return list<string>
     */
    public function schema(): array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $paths = [database_path('migrations'), ...$migrator->paths()];

        // The addon boot cache can be missing, so bundled addons may not have registered their paths.
        foreach (AddonRoots::bundled() as $name) {
            $dir = AddonRoots::bundledSource().'/'.$name.'/database/migrations';

            if (is_dir($dir)) {
                $paths[] = $dir;
            }
        }

        // Without a boot cache, enabled third-party addons have not registered their paths either.
        try {
            if (Schema::hasTable('addons')) {
                foreach (app(AddonRegistry::class)->enabled() as $addon) {
                    $dir = $addon->getPath().'/database/migrations';

                    if (is_dir($dir)) {
                        $paths[] = $dir;
                    }
                }
            }
        } catch (Throwable) {
            // No usable addons table yet; core and bundled paths still apply.
        }

        $unique = [];
        foreach ($paths as $path) {
            $unique[realpath($path) ?: $path] ??= $path;
        }

        return array_values($unique);
    }

    /**
     * @return list<string>
     */
    public function data(): array
    {
        return [database_path('migrations_data')];
    }

    /**
     * @return list<string>
     */
    public function pendingSchema(): array
    {
        return $this->pending(app('migration.repository'), $this->schema());
    }

    /**
     * @return list<string>
     */
    public function pendingData(): array
    {
        return $this->pending(app('migration.data.repository'), $this->data());
    }

    /**
     * Migration names in $paths that $repository has not recorded.
     *
     * @param  list<string> $paths
     * @return list<string>
     */
    public function pending(MigrationRepositoryInterface $repository, array $paths): array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $files = array_map(strval(...), array_keys($migrator->getMigrationFiles($paths)));
        $ran = $repository->repositoryExists() ? $repository->getRan() : [];

        return array_values(array_diff($files, $ran));
    }
}
