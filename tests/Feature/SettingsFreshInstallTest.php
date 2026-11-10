<?php

declare(strict_types=1);

use App\Services\Installer\MigrationService;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * A fresh install: the schema migrations (RefreshDatabase) and then every data
 * migration, as the installer runs them. Settings need no seeding.
 */
it('reads every setting as its default on a fresh install, plus what the data migrations set', function (): void {
    // TestCase pins these two for the rest of the suite.
    DB::table('settings')->delete();
    reloadSettings();

    Artisan::call('migrate-data', [
        '--force'    => true,
        '--realpath' => true,
        '--path'     => app(MigrationService::class)->getMigrationPaths('migrations_data'),
    ]);
    reloadSettings();

    // Set by the registry identity data migration.
    expect(setting('registry.va_global_id'))->not->toBe('');

    $changedFromDefault = app(SettingsRegistry::class)->all()
        ->reject(fn (SettingDefinition $definition): bool => $definition->key === 'registry.va_global_id')
        ->filter(fn (SettingDefinition $definition): bool => setting($definition->key) !== $definition->present($definition->default))
        ->map(fn (SettingDefinition $definition): string => $definition->key)
        ->values()
        ->all();

    expect($changedFromDefault)->toBe([]);
})->skip(
    fn (): bool => in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true),
    'A data migration creates tables, and MySQL commits DDL outside the test transaction.',
);
