<?php

declare(strict_types=1);

namespace App\Features\Addons\Actions;

use Illuminate\Support\Facades\Schema;

/**
 * Drops an addon's declared tables, ignoring any that do not exist.
 *
 * Not final: tests mock it to force a failed uninstall.
 */
class DropAddonTables
{
    /**
     * Reverse declared order with foreign keys off, so references between the
     * addon's own tables do not block the drop.
     *
     * @param list<string> $tables
     */
    public function handle(array $tables): void
    {
        if ($tables === []) {
            return;
        }

        $schema = Schema::connection(config('database.default'));

        $schema->disableForeignKeyConstraints();

        try {
            foreach (array_reverse($tables) as $table) {
                $schema->dropIfExists($table);
            }
        } finally {
            $schema->enableForeignKeyConstraints();
        }
    }
}
