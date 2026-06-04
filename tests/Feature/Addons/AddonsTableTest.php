<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the addons table with all D-06 columns', function (): void {
    expect(Schema::hasTable('addons'))->toBeTrue();

    expect(Schema::hasColumns('addons', [
        'id',
        'registry_id',
        'type',
        'version',
        'namespace',
        'path',
        'enabled',
        'installed_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('seeds three bundled modules with null registry_id', function (): void {
    expect(DB::table('addons')->whereNull('registry_id')->count())->toBe(3);
});

it('seeds the Awards module with correct namespace and null version', function (): void {
    $awards = DB::table('addons')->where('namespace', 'Modules\\Awards')->first();

    expect($awards)->not->toBeNull()
        ->and($awards->registry_id)->toBeNull()
        ->and($awards->version)->toBeNull();
});

it('seeds the VMSAcars module with version 1.1.0', function (): void {
    $vmsacars = DB::table('addons')->where('namespace', 'Modules\\VMSAcars')->first();

    expect($vmsacars)->not->toBeNull()
        ->and($vmsacars->version)->toBe('1.1.0');
});

it('seeds all bundled rows with type module enabled true and path inside modules directory', function (): void {
    $rows = DB::table('addons')->whereNull('registry_id')->get();

    foreach ($rows as $row) {
        expect($row->type)->toBe('module')
            ->and((bool) $row->enabled)->toBeTrue()
            ->and(str_starts_with((string) $row->path, base_path('modules')))->toBeTrue();
    }
});

it('drops the addons table on rollback', function (): void {
    $migration = require database_path('migrations/2026_06_04_000001_create_addons_table.php');
    $migration->down();

    expect(Schema::hasTable('addons'))->toBeFalse();
});
