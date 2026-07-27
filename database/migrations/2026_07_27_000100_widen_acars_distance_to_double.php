<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * `acars`.`distance` was created as an unsigned integer
     * (`2025_01_13_003704_create_phpvms_table.php`), so it cannot hold a
     * fractional distance at all. `pirep_positions`.`distance` is a double
     * holding the same quantity, and leaving the two disagreeing about whether
     * 12.4 nm is representable would make the position row and the breadcrumb it
     * came from report different distances for the same point.
     *
     * The widening is lossless: unsigned int tops out at 4,294,967,295, well
     * inside the range where a double represents integers exactly.
     *
     * Plain DOUBLE, not DOUBLE UNSIGNED, which is deprecated as of MySQL 8.0.17.
     *
     * A new migration rather than an edit to the shipped one — the shipped
     * migration has already run everywhere and editing it would change nothing
     * on an existing install while diverging from what those installs actually
     * have.
     */
    public function up(): void
    {
        if (!Schema::hasTable('acars')) {
            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            $table->double('distance')->nullable()->change();
        });
    }

    /**
     * Back to unsigned integer. Any fractional part written while the column was
     * a double is rounded away by the narrowing — the schema is restored, the
     * precision is not.
     */
    public function down(): void
    {
        if (!Schema::hasTable('acars')) {
            return;
        }

        Schema::table('acars', function (Blueprint $table): void {
            $table->unsignedInteger('distance')->nullable()->change();
        });
    }
};
