<?php

declare(strict_types=1);

use App\Support\Settings\LegacySettingsCopier;
use Illuminate\Database\Migrations\Migration;

/**
 * Copy the settings backups into the current settings tables, after every
 * historical data migration has written the backups.
 *
 * The schema migration that created the current tables copied them once
 * already; this second copy carries over what the data migrations dated before
 * this one changed on this run (on an upgrade from an older version, or on a
 * fresh install, where they create rows such as `registry.va_global_id`). The
 * backups are not written again after this.
 *
 * Must stay the newest data migration that reads the backups. A later change
 * to setting values is a data migration on the current tables (a
 * Spatie\LaravelSettings\Migrations\SettingsMigration works here).
 */
return new class() extends Migration
{
    public function up(): void
    {
        $copier = app(LegacySettingsCopier::class);

        $copier->copy();
        $copier->logUndeclaredAddonKeys();
    }

    public function down(): void {}
};
