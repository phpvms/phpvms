<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL only: convert `json` columns to `jsonb`.
 *
 * PostgreSQL's `json` type stores the document verbatim and defines no equality
 * operator, so any `SELECT DISTINCT t.*` over a table carrying one fails with
 * SQLSTATE 42883. Filament's relation-manager attach modals do exactly that
 * (`RelationshipJoiner::prepareQueryForNoConstraints` hardcodes
 * `->distinct()->select('subfleets.*')`), so attaching a subfleet to a flight,
 * rank, type rating or bundle is impossible while `subfleets.route_types` is
 * `json`.
 *
 * `jsonb` has the operator, is indexable, and is what Laravel's `jsonb()`
 * already emits for PostgreSQL. MySQL and SQLite are unaffected: their `json`
 * types compare fine and Laravel maps `jsonb()` to `json`/`text` there, so this
 * migration is a no-op on those drivers.
 */
return new class() extends Migration
{
    /** @var array<string, string> table => column */
    private const array COLUMNS = [
        'subfleets'          => 'route_types',
        'expenses'           => 'flight_type',
        'notifications'      => 'data',
        'activity_log'       => 'properties',
        'failed_import_rows' => 'data',
    ];

    public function up(): void
    {
        $this->convert('jsonb');
    }

    public function down(): void
    {
        $this->convert('json');
    }

    private function convert(string $type): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $table => $column) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (!Schema::hasColumn($table, $column)) {
                continue;
            }
            DB::statement(sprintf(
                'alter table %s alter column %s type %s using %s::%s',
                $table,
                $column,
                $type,
                $column,
                $type
            ));
        }
    }
};
