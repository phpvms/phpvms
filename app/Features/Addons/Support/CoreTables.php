<?php

declare(strict_types=1);

namespace App\Features\Addons\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * The database tables that core owns. An addon uninstall never drops one of
 * them, whatever the addon declares.
 *
 * The set is the union of three sources, which only ever add to it:
 *  - the string-literal tables of `Schema::create()` and `Schema::rename()` in
 *    core's `database/migrations`;
 *  - `getTable()` of every model in `App\Models`;
 *  - the table names that come from configuration: the permission, migration,
 *    queue, batch, failed-job, cache and session tables.
 */
final class CoreTables
{
    /**
     * @return list<string> sorted, without duplicates
     */
    public static function all(): array
    {
        $tables = [
            ...self::fromMigrations(),
            ...self::fromModels(),
            ...self::fromConfig(),
        ];

        $tables = array_values(array_unique(array_filter($tables, fn (string $table): bool => $table !== '')));
        sort($tables);

        return $tables;
    }

    public static function contains(string $table): bool
    {
        return in_array($table, self::all(), true);
    }

    /**
     * @return list<string>
     */
    private static function fromMigrations(): array
    {
        $directory = database_path('migrations');

        if (!is_dir($directory)) {
            return [];
        }

        $tables = [];

        foreach (File::files($directory) as $file) {
            $source = (string) file_get_contents($file->getPathname());

            if (preg_match_all('/Schema::(?:connection\([^)]*\)->)?create\(\s*[\'"]([^\'"]+)[\'"]/', $source, $created) > 0) {
                $tables = [...$tables, ...$created[1]];
            }

            if (preg_match_all('/Schema::(?:connection\([^)]*\)->)?rename\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $source, $renamed) > 0) {
                $tables = [...$tables, ...$renamed[1], ...$renamed[2]];
            }
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    private static function fromModels(): array
    {
        $directory = app_path('Models');

        if (!is_dir($directory)) {
            return [];
        }

        $tables = [];

        foreach (File::allFiles($directory) as $file) {
            /** @var SplFileInfo $file */
            $relative = substr($file->getPathname(), strlen($directory) + 1, -strlen('.php'));
            $class = 'App\\Models\\'.str_replace('/', '\\', $relative);

            try {
                if (!class_exists($class) || !is_subclass_of($class, Model::class) || new ReflectionClass($class)->isAbstract()) {
                    continue;
                }

                $tables[] = new $class()->getTable();
            } catch (Throwable) {
                continue;
            }
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    private static function fromConfig(): array
    {
        $names = [
            ...array_values((array) config('permission.table_names', [])),
            config('database.migrations.table'),
            config('queue.connections.database.table'),
            config('queue.batching.table'),
            config('queue.failed.table'),
            config('cache.stores.database.table'),
            config('cache.stores.database.lock_table'),
            config('session.table'),
        ];

        if (is_string(config('database.migrations'))) {
            $names[] = config('database.migrations');
        }

        return array_values(array_filter($names, is_string(...)));
    }
}
