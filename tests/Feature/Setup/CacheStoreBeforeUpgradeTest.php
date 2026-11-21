<?php

declare(strict_types=1);

use App\Features\Setup\Support\SetupCache;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Providers\AppServiceProvider;

beforeEach(function (): void {
    $this->storage = storage_path();
    $this->tmp = sys_get_temp_dir().'/phpvms-cache-store-'.uniqid();
    mkdir($this->tmp.'/framework', 0777, true);
    app()->useStoragePath($this->tmp);
    config(['cache.default' => 'database']);
});

afterEach(function (): void {
    SetupCache::$buildingConfig = false;
    SetupCache::$realStore = null;
    @unlink(UpgradeCompleted::defaultPath());
    @unlink(storage_path('framework/down'));
    @unlink(SetupRunState::defaultPath());
    @rmdir($this->tmp.'/framework');
    @rmdir($this->tmp);
    app()->useStoragePath($this->storage);
});

function writeRunStatus(string $status): void
{
    file_put_contents(SetupRunState::defaultPath(), json_encode(['status' => $status]));
}

test('the cache store is the array store until the upgrade completes', function (): void {
    new AppServiceProvider(app())->register();

    expect(config('cache.default'))->toBe('array');
});

test('the swapped-out store is remembered so setup can still clear it', function (): void {
    new AppServiceProvider(app())->register();

    expect(SetupCache::$realStore)->toBe('database');
});

test('once the run has brought the site up, workers it reloads use the real store before the upgrade completes', function (): void {
    file_put_contents(SetupRunState::defaultPath(), json_encode(['status' => 'running', 'steps' => ['maintenance_up' => ['status' => 'done']]]));

    new AppServiceProvider(app())->register();

    expect(config('cache.default'))->toBe('database');
});

test('the cache store is kept once the upgrade has completed', function (): void {
    touch(UpgradeCompleted::defaultPath());

    new AppServiceProvider(app())->register();

    expect(config('cache.default'))->toBe('database');
});

test('the cache store is the array store while an update runs in maintenance mode', function (): void {
    touch(UpgradeCompleted::defaultPath());
    touch(storage_path('framework/down'));
    writeRunStatus('running');

    new AppServiceProvider(app())->register();

    expect(config('cache.default'))->toBe('array');
});

test('the cache store is kept once the update run is done', function (): void {
    touch(UpgradeCompleted::defaultPath());
    touch(storage_path('framework/down'));
    writeRunStatus('done');

    new AppServiceProvider(app())->register();

    expect(config('cache.default'))->toBe('database');
});

test('an unreadable run state in maintenance mode counts as a run in progress', function (): void {
    touch(UpgradeCompleted::defaultPath());
    touch(storage_path('framework/down'));
    file_put_contents(SetupRunState::defaultPath(), '{not json');

    new AppServiceProvider(app())->register();

    expect(config('cache.default'))->toBe('array');
});

test('the cache store is kept while a config cache is built', function (): void {
    SetupCache::$buildingConfig = true;

    new AppServiceProvider(app())->register();

    expect(config('cache.default'))->toBe('database');
});
