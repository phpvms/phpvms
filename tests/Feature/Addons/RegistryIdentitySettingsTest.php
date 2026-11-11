<?php

declare(strict_types=1);

use App\Models\LegacySetting;
use Illuminate\Support\Facades\DB;

function runIdentityMigration(): void
{
    $migration = require base_path('database/migrations_data/2026_08_02_000000_registry_identity_settings.php');
    $migration->up();
}

function settingValue(string $key): ?string
{
    return DB::table('settings_backup')->where('id', LegacySetting::formatKey($key))->value('value');
}

// A symlinked addon migration may already have set the id during RefreshDatabase.
beforeEach(function (): void {
    seedLegacySettings();
    DB::table('kvp')->where('key', 'va_global_id')->delete();
    DB::table('settings_backup')->where('id', LegacySetting::formatKey('va_global_id'))->update(['value' => '']);
});

it('migrates an existing kvp va_global_id and is idempotent', function (): void {
    DB::table('kvp')->insert(['key' => 'va_global_id', 'value' => 'EXISTINGID']);

    runIdentityMigration();
    expect(settingValue('va_global_id'))->toBe('EXISTINGID');

    // Re-running changes nothing.
    runIdentityMigration();
    expect(settingValue('va_global_id'))->toBe('EXISTINGID');
    expect(DB::table('kvp')->where('key', 'va_global_id')->count())->toBe(1);
});

it('mints an id into both settings and kvp when none exists', function (): void {
    expect(DB::table('kvp')->where('key', 'va_global_id')->exists())->toBeFalse();

    runIdentityMigration();

    $value = settingValue('va_global_id');
    expect($value)->toHaveLength(12);
    expect(DB::table('kvp')->where('key', 'va_global_id')->value('value'))->toBe($value);
});

it('stores identity rows as hidden and excludes them from the settings UI query', function (): void {
    runIdentityMigration();

    expect(settingRowType('va_global_id'))->toBe('hidden');
    expect(settingRowType('registry.public_key'))->toBe('hidden');

    // The settings pages render `LegacySetting::where('type', '!=', 'hidden')`.
    $visible = LegacySetting::where('type', '!=', 'hidden')->pluck('id')->all();
    expect($visible)->not->toContain('va_global_id');
    expect($visible)->not->toContain('registry_public_key');
});

function settingRowType(string $key): ?string
{
    return DB::table('settings_backup')->where('id', LegacySetting::formatKey($key))->value('type');
}
