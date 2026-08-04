<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

function runIdentityMigration(): void
{
    $migration = require base_path('database/migrations_data/2026_08_02_000000_registry_identity_settings.php');
    $migration->up();
}

function settingValue(string $key): ?string
{
    return DB::table('settings')->where('id', Setting::formatKey($key))->value('value');
}

it('migrates an existing kvp va_global_id and is idempotent', function (): void {
    DB::table('kvp')->insert(['key' => 'va_global_id', 'value' => 'EXISTINGID']);

    runIdentityMigration();
    expect(settingValue('va_global_id'))->toBe('EXISTINGID');

    // Re-running changes nothing.
    runIdentityMigration();
    expect(settingValue('va_global_id'))->toBe('EXISTINGID');
    expect(DB::table('kvp')->where('key', 'va_global_id')->count())->toBe(1);
});

it('mints a ULID into both settings and kvp when none exists', function (): void {
    expect(DB::table('kvp')->where('key', 'va_global_id')->exists())->toBeFalse();

    runIdentityMigration();

    $value = settingValue('va_global_id');
    expect($value)->toHaveLength(26);
    expect(DB::table('kvp')->where('key', 'va_global_id')->value('value'))->toBe($value);
});

it('stores identity rows as hidden and excludes them from the settings UI query', function (): void {
    runIdentityMigration();

    expect(settingRowType('va_global_id'))->toBe('hidden');
    expect(settingRowType('registry.public_key'))->toBe('hidden');

    // The settings pages render `Setting::where('type', '!=', 'hidden')`.
    $visible = Setting::where('type', '!=', 'hidden')->pluck('id')->all();
    expect($visible)->not->toContain('va_global_id');
    expect($visible)->not->toContain('registry_public_key');
});

function settingRowType(string $key): ?string
{
    return DB::table('settings')->where('id', Setting::formatKey($key))->value('type');
}
