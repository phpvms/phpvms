<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\DB;

final class ConvertToInnodb implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::Innodb;
    }

    public function applies(StepContext $ctx): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function run(StepContext $ctx): void
    {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $prefix = $connection->getTablePrefix();

        // LEFT() instead of LIKE so a "_" or "%" in the prefix is not a wildcard.
        $tables = array_map(
            static fn (object $row): string => (string) $row->name,
            $connection->select(
                "SELECT TABLE_NAME AS name FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND ENGINE = 'MyISAM' AND TABLE_TYPE = 'BASE TABLE'
                 AND LEFT(TABLE_NAME, ?) = ? ORDER BY TABLE_NAME",
                [strlen($prefix), $prefix]
            )
        );

        $total = count($tables);
        foreach ($tables as $i => $table) {
            // $table already carries the prefix, so wrap the raw identifier.
            $connection->statement('ALTER TABLE '.$grammar->wrap($table).' ENGINE=InnoDB');
            ($ctx->progress)($i + 1, $total, $table);
        }
    }
}
