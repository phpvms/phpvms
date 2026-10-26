<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen every airport id column to 16 characters, and make the comparison
 * case-sensitive on MySQL and MariaDB.
 *
 * Airports key on their ICAO code today, which is what 2025_02_04_165053 sized
 * these columns for. The plan is to move them onto generated ids -- a nanoid at
 * 16 characters -- and leave `icao` as an ordinary attribute, so the id columns
 * need the room before anything can start writing one.
 *
 * The collation goes with the width. These tables are created
 * `utf8mb4_unicode_ci`, which is case-INsensitive, so `kjfk` and `KJFK` are one
 * id there while Postgres treats them as two. That divergence was harmless
 * while an id was an upper-case ICAO; it is not harmless for a generated id,
 * which carries mixed case by construction. `utf8mb4_bin` because it is the
 * one case-sensitive utf8mb4 collation both servers have had for the whole
 * range phpVMS supports -- MySQL's `utf8mb4_0900_as_cs` does not exist on
 * MariaDB, which is what most installs run.
 *
 * Applied to all twelve referencing columns as well as `airports.id`: MySQL
 * raises "Illegal mix of collations" when a comparison puts two differently
 * collated columns on either side, so these have to move together.
 *
 * Only id columns move. `airports.icao` and `airports.iata` stay as they are,
 * case-insensitive included; they hold real codes, and a code is matched by
 * what it spells rather than how it was typed.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('aircraft', function (Blueprint $table): void {
            $this->airportId($table, 'airport_id', nullable: true);
            $this->airportId($table, 'hub_id', nullable: true);
        });

        Schema::table('airports', function (Blueprint $table): void {
            $this->airportId($table, 'id');
        });

        Schema::table('flights', function (Blueprint $table): void {
            $this->airportId($table, 'dpt_airport_id');
            $this->airportId($table, 'arr_airport_id');
            $this->airportId($table, 'alt_airport_id', nullable: true);
        });

        Schema::table('pireps', function (Blueprint $table): void {
            $this->airportId($table, 'dpt_airport_id');
            $this->airportId($table, 'arr_airport_id');
            $this->airportId($table, 'alt_airport_id', nullable: true);
        });

        Schema::table('subfleets', function (Blueprint $table): void {
            $this->airportId($table, 'hub_id', nullable: true);
        });

        Schema::table('users', function (Blueprint $table): void {
            $this->airportId($table, 'home_airport_id', nullable: true);
            $this->airportId($table, 'curr_airport_id', nullable: true);
        });
    }

    public function down(): void
    {
        // Not reversed on purpose: narrowing back to 8 would truncate any id
        // that has already used the extra room.
    }

    /**
     * Redefine one airport id column at its new width.
     *
     * `->change()` rebuilds a column from the definition it is handed, so every
     * attribute the column should keep has to be restated here -- a missing
     * `nullable` would silently convert the column to NOT NULL. The collation
     * is set only where it means something: Postgres already compares
     * case-sensitively, and SQLite's default is BINARY, so naming a MySQL
     * collation on either would just be a type its grammar has to reject.
     */
    private function airportId(Blueprint $table, string $column, bool $nullable = false): void
    {
        $definition = $table->string($column, 16)->nullable($nullable);

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $definition->collation('utf8mb4_bin');
        }

        $definition->change();
    }
};
