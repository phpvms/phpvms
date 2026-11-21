<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * One row per filed PIREP holding a self-contained snapshot of its flight,
     * aircraft, and trimmed SimBrief plan, so detail views survive the source
     * rows being deleted or changed.
     */
    public function up(): void
    {
        // MySQL adds the indexes and foreign keys after CREATE TABLE, so an empty table
        // is a create that was cut short: start it over. One with rows is complete.
        if (Schema::hasTable('pirep_archive')) {
            if (DB::table('pirep_archive')->exists()) {
                return;
            }

            Schema::drop('pirep_archive');
        }

        Schema::create('pirep_archive', function (Blueprint $table): void {
            $pirepId = $table->string('pirep_id', 36)->primary();

            // Only the FK column takes `pireps`.`id`'s collation; the table keeps utf8mb4.
            if (($parent = $this->parentCollation('pireps', 'id')) !== null) {
                $pirepId->charset($parent[0])->collation($parent[1]);
            }

            // Provenance: the flight the pirep was filed against, kept here so
            // it survives even if pireps.flight_id is ever cleared. Dangling
            // (flight hard-deleted) is fine; null means a manual pirep.
            $table->string('flight_id', 36)->nullable();
            $table->json('flight')->nullable();
            $table->json('aircraft')->nullable();
            $table->json('simbrief')->nullable();
            $table->json('navlog')->nullable();
            $table->timestamps();

            $table->foreign('pirep_id')->references('id')->on('pireps')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pirep_archive');
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
