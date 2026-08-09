<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the `acars.log` column from VARCHAR(255) to TEXT.
 *
 * The ACARS contract's typed flight events (contract.proto `Event`: type,
 * category, payload, position) are stored on LOG-type `acars` rows as a JSON
 * blob in the `log` column. The legacy column only held a short display
 * string; a full event payload overflows 255 chars, so it is widened to TEXT.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('acars', function (Blueprint $table): void {
            $table->text('log')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Narrowing back to VARCHAR(255) would silently truncate (or, in strict
        // mode, fail) any log that grew past 255 chars while widened. Refuse the
        // rollback instead of losing data — the operator must shorten/clear
        // those rows first.
        $oversized = DB::table('acars')->whereRaw('LENGTH(log) > 255')->count();
        if ($oversized > 0) {
            throw new RuntimeException(
                sprintf('Cannot roll back: %d acars.log row(s) exceed 255 characters. ', $oversized)
                .'Shorten or clear them before narrowing the column.'
            );
        }

        Schema::table('acars', function (Blueprint $table): void {
            $table->string('log')->nullable()->change();
        });
    }
};
