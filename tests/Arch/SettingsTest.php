<?php

declare(strict_types=1);

use App\Models\LegacySetting;
use Illuminate\Support\Facades\File;

/*
 * The settings tables were rebuilt for typed settings classes; the old ones are
 * kept as `settings_backup` and `addon_settings_backup`. The historical data
 * migrations still write the backups, and the newest of them copies the
 * backups into the current tables. These rules keep that ordering sound.
 */

/** The data migration that copies the backups into the current tables. */
const SETTINGS_COPY_MIGRATION = '2026_11_10_100000_copy_settings_from_backups.php';

/**
 * Data migrations dated before the copy: they run against the backups.
 *
 * @return array<string, string> file name => contents
 */
function historicalDataMigrations(): array
{
    return collect(File::files(database_path('migrations_data')))
        ->filter(fn (SplFileInfo $file): bool => strcmp($file->getFilename(), SETTINGS_COPY_MIGRATION) < 0)
        ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getFilename() => (string) file_get_contents($file->getPathname())])
        ->all();
}

it('keeps historical data migrations off the current settings tables and helpers', function (): void {
    $forbidden = [
        '/[^_]setting\s*\(/'                           => 'the setting() helper',
        '/setting_save\s*\(/'                          => 'the setting_save() helper',
        '/addon_setting(_save)?\s*\(/'                 => 'the addon settings helpers',
        "/DB::table\\(\\s*'(addon_)?settings'\\s*\\)/" => 'the current settings tables',
        '/(?<![A-Za-z\\\\])Setting(::|\\b(?!s))/'      => 'the removed App\\Models\\Setting model',
    ];

    foreach (historicalDataMigrations() as $file => $contents) {
        // Comments may name the old model and helpers; code may not.
        $code = preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $contents);

        foreach ($forbidden as $pattern => $what) {
            expect(preg_match($pattern, (string) $code))->toBe(0, "{$file} uses {$what}; it runs against settings_backup.");
        }
    }
});

it('keeps the copy the newest data migration that reads the backups', function (): void {
    $later = collect(File::files(database_path('migrations_data')))
        ->filter(fn (SplFileInfo $file): bool => strcmp($file->getFilename(), SETTINGS_COPY_MIGRATION) > 0)
        ->filter(fn (SplFileInfo $file): bool => str_contains((string) file_get_contents($file->getPathname()), '_backup'))
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->values()
        ->all();

    expect($later)->toBe([]);
});

arch('the legacy settings model is only for historical data migrations')
    ->expect(LegacySetting::class)
    ->not->toBeUsedIn('App');
