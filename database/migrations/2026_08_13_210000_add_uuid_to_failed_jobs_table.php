<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Add the `uuid` column Laravel's failed-job recorder requires.
 *
 * `failed_jobs` was created without it (`2025_01_13_003704_create_phpvms_table.php`,
 * the `failed_jobs` block), but `config/queue.php:126` defaults the failed
 * driver to `database-uuids` — `Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider`,
 * which inserts a `uuid` on every log() and looks rows up by it.
 *
 * The effect was that a failing job could not be RECORDED: the insert threw
 * `SQLSTATE[42703] Undefined column: uuid`, which surfaced in the worker output
 * in place of whatever the job actually threw. So every queue failure on every
 * phpVMS install was both unrecorded and undiagnosable.
 *
 * Nullable rather than Laravel's canonical `->unique()` NOT NULL, because an
 * install that had `QUEUE_FAILED_DRIVER=database` set could hold rows written
 * by the non-uuid provider, and a NOT NULL column cannot be added to a table
 * with existing rows. Those rows are backfilled below so they stay addressable
 * by `queue:retry`; both MySQL and Postgres permit repeated NULLs under a
 * unique index, so the constraint is safe either way.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('failed_jobs')) {
            return;
        }

        if (Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table): void {
            $table->string('uuid')->nullable()->unique();
        });

        DB::table('failed_jobs')
            ->whereNull('uuid')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('failed_jobs')
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('failed_jobs')) {
            return;
        }

        if (!Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
