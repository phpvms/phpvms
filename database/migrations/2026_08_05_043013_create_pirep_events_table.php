<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pirep_events')) {
            return;
        }

        Schema::create('pirep_events', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('pirep_id', 36)->index();
            $acarsId = $table->string('acars_id', 36)->nullable();

            // Only the FK column takes `acars`.`id`'s collation; the table keeps utf8mb4.
            if (($parent = $this->parentCollation('acars', 'id')) !== null) {
                $acarsId->charset($parent[0])->collation($parent[1]);
            }

            $table->string('client_event_id')->nullable();
            $table->string('type')->nullable()->index();
            $table->string('category')->index();
            $table->string('phase')->nullable();
            $table->decimal('lat', 10, 5)->nullable();
            $table->decimal('lon', 11, 5)->nullable();
            $table->decimal('altitude_msl')->nullable();
            $table->text('log')->nullable();
            $table->json('details')->nullable();
            $table->string('sim_time')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['pirep_id', 'client_event_id']);

            // Defined inline (not via a later Schema::table()) so SQLite, which
            // cannot ALTER TABLE ADD CONSTRAINT, still gets the FK: it supports
            // foreign keys declared at CREATE TABLE time.
            $table->foreign('acars_id')->references('id')->on('acars')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pirep_events');
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
