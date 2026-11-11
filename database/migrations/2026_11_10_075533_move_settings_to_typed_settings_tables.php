<?php

declare(strict_types=1);

use App\Support\Settings\LegacySettingsCopier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move core and addon settings onto the spatie/laravel-settings schema.
 *
 * The current `settings` and `addon_settings` tables are renamed to
 * `settings_backup` and `addon_settings_backup` and kept, and new tables with
 * the package's schema take their names (see config/settings.php).
 *
 * The values are copied into the new tables right away. The historical data
 * migrations in `database/migrations_data/` write the backup tables, so
 * pending ones still apply on an upgrade; the final data migration copies the
 * backups into the new tables again after them.
 *
 * down() drops the new tables and renames the backups back, restoring the
 * previous schema with the values it held before the upgrade.
 */
return new class() extends Migration
{
    /** @var array<string, string> live table => backup table */
    private const array TABLES = [
        'settings'       => 'settings_backup',
        'addon_settings' => 'addon_settings_backup',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $backup) {
            if (Schema::hasTable($table) && !Schema::hasTable($backup)) {
                Schema::rename($table, $backup);
            }

            if (!Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $blueprint): void {
                    $blueprint->id();
                    $blueprint->string('group');
                    $blueprint->string('name');
                    $blueprint->boolean('locked')->default(false);
                    $blueprint->json('payload');
                    $blueprint->timestamps();

                    $blueprint->unique(['group', 'name']);
                });
            }
        }

        // First copy, so settings hold their values as soon as `migrate`
        // finishes rather than reading as defaults until `migrate-data` runs.
        app(LegacySettingsCopier::class)->copy();
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $backup) {
            if (!Schema::hasTable($backup)) {
                continue;
            }

            Schema::dropIfExists($table);
            Schema::rename($backup, $table);
        }
    }
};
