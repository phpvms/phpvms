<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * One row per PIREP holding its last-known position. Presence of a row is map
     * membership. Column names follow `acars` and `pireps`, not the wire protocol,
     * so units come from DistanceCast/FuelCast. Everything but the timestamps is NOT NULL.
     */
    public function up(): void
    {
        if (Schema::hasTable('pirep_positions')) {
            return;
        }

        Schema::create('pirep_positions', function (Blueprint $table): void {
            // The parent key is the primary key - one row per PIREP is the point.
            $pirepId = $table->string('pirep_id', 36)->primary();

            // Only the FK column takes `pireps`.`id`'s collation; the table keeps utf8mb4.
            if (($parent = $this->parentCollation('pireps', 'id')) !== null) {
                $pirepId->charset($parent[0])->collation($parent[1]);
            }

            // Denormalised for pilot-scoped queries. Safe while a PIREP's owner
            // cannot change.
            $table->unsignedInteger('user_id')->index();

            // PirepPhase, same storage as `pireps`.`status`.
            $table->string('phase', 3)->default('SCH');

            $table->decimal('lat', 10, 5)->default(0);
            $table->decimal('lon', 11, 5)->default(0);
            $table->unsignedSmallInteger('heading')->default(0);

            // Plain DOUBLE: UNSIGNED is deprecated in MySQL 8.0.17, and `vs` is signed.
            $table->double('distance')->default(0);
            $table->double('altitude_agl')->default(0);
            $table->double('altitude_msl')->default(0);
            $table->double('vs')->default(0);

            $table->unsignedInteger('gs')->default(0);
            $table->unsignedInteger('ias')->default(0);

            // Minutes, matching `pireps`.`flight_time`.
            $table->unsignedInteger('flight_time')->default(0);
            $table->decimal('fuel_used')->default(0);

            // `updated_at` is the liveness clock: position batches only, unlike
            // `pireps`.`updated_at`, which any write bumps.
            $table->timestamps();

            // At create time, so it lands on SQLite too - unlike the `acars` one.
            $table->foreign('pirep_id')->references('id')->on('pireps')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pirep_positions');
    }

    /**
     * The parent key's charset and collation: v7 sites never pinned one, and
     * MySQL rejects an FK across mismatched collations. Null off MySQL/MariaDB.
     *
     * @return array{string, string}|null
     */
    private function parentCollation(string $table, string $column): ?array
    {
        $connection = Schema::getConnection();

        if (!in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return null;
        }

        $row = $connection->selectOne(
            'select CHARACTER_SET_NAME as charset, COLLATION_NAME as collation
            from information_schema.COLUMNS
            where TABLE_SCHEMA = ? and TABLE_NAME = ? and COLUMN_NAME = ?',
            [$connection->getDatabaseName(), $connection->getTablePrefix().$table, $column]
        );

        return $row?->collation !== null ? [$row->charset, $row->collation] : null;
    }
};
