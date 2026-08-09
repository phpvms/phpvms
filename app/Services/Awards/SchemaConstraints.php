<?php

declare(strict_types=1);

namespace App\Services\Awards;

use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns a table's live schema into a query-builder vocabulary. The per-table
 * decisions -- which columns to skip and which ones need a hand-written
 * constraint -- stay with `UserConstraints` and `PirepConstraints`; only the
 * mechanical part lives here.
 *
 * Resolution order per column is: override map wins, then the denylist, then
 * the column type decides the constraint class.
 */
class SchemaConstraints
{
    /**
     * @param  array<int, string>        $denied
     * @param  array<string, Constraint> $overrides
     * @return array<int, Constraint>
     */
    public static function build(string $table, array $denied, array $overrides): array
    {
        $constraints = [];

        foreach (Schema::getColumns($table) as $column) {
            $name = $column['name'];

            if (array_key_exists($name, $overrides)) {
                $constraints[] = $overrides[$name];

                continue;
            }

            if (in_array($name, $denied, true)) {
                continue;
            }

            $constraint = self::fromColumn($column);

            if ($constraint instanceof Constraint) {
                $constraints[] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * Map a schema column onto a constraint class. Covers the type names
     * reported by every driver phpVMS supports -- Postgres says `int4` and
     * `bool`, MySQL and SQLite say `integer` and `tinyint(1)`.
     *
     * Nullability is applied here rather than by the caller: `nullable()`
     * comes from a trait on the concrete constraint classes, not from the
     * `Constraint` base this returns.
     *
     * @param array{name: string, type_name: string, type: string, nullable: bool} $column
     */
    private static function fromColumn(array $column): ?Constraint
    {
        $name = $column['name'];
        $type = $column['type_name'];
        $isNullable = $column['nullable'];

        // Numeric foreign keys. Varchar ids (ICAO codes) fall through.
        if (str_ends_with($name, '_id') && !in_array($type, ['char', 'varchar', 'text', 'string'], true)) {
            return null;
        }

        return match (true) {
            in_array($type, ['char', 'varchar', 'text', 'string'], true) => TextConstraint::make($name)->nullable($isNullable),
            in_array($type, ['bool', 'boolean'], true),
            $type === 'tinyint' && str_contains($column['type'], '(1)')                                                     => BooleanConstraint::make($name)->nullable($isNullable),
            in_array($type, ['int', 'int2', 'int4', 'int8', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint'], true) => NumberConstraint::make($name)->integer()->nullable($isNullable),
            in_array($type, ['numeric', 'decimal', 'real', 'float', 'float4', 'float8', 'double'], true)                    => NumberConstraint::make($name)->nullable($isNullable),
            in_array($type, ['date', 'datetime', 'timestamp', 'timestamptz'], true)                                         => DateConstraint::make($name)->nullable($isNullable),
            default                                                                                                         => null,
        };
    }
}
