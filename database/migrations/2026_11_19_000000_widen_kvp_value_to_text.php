<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Post-upgrade task state is JSON in kvp rows, which outgrows varchar(191).
 * No charset/collation on the column: legacy sites keep their table collation.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('kvp', 'value')) {
            return;
        }

        // Safe to re-run after a partial upgrade, or on a site that widened it by hand.
        if (in_array(Schema::getColumnType('kvp', 'value'), ['text', 'mediumtext', 'longtext'], true)) {
            return;
        }

        Schema::table('kvp', function (Blueprint $table): void {
            $table->text('value')->change();
        });
    }
};
