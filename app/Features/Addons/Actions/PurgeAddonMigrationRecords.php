<?php

declare(strict_types=1);

namespace App\Features\Addons\Actions;

use App\Models\Addon;
use Illuminate\Database\Migrations\Migrator;

/**
 * Deletes an addon's rows from the migrations table without running down(),
 * so a reinstall re-runs them against freshly dropped tables.
 */
final readonly class PurgeAddonMigrationRecords
{
    /**
     * @param string|null $addonPath where the files are, when moved from `$addon->getPath()`
     */
    public function handle(Addon $addon, ?string $addonPath = null): void
    {
        $path = ($addonPath ?? $addon->getPath()).'/database/migrations';

        if (!is_dir($path)) {
            return;
        }

        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $migrator->setConnection(config('database.default'));

        $names = array_keys($migrator->getMigrationFiles([$path]));
        $repository = $migrator->getRepository();

        foreach ($names as $name) {
            $repository->delete((object) ['migration' => $name]);
        }
    }
}
