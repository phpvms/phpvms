<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Support\BootCache;
use App\Models\Addon;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Modules\AddonManager\Filament\Pages\Addons as AddonsPage;
use Modules\AddonManager\Services\RegistryClient;

beforeEach(function (): void {
    Cache::flush();

    app(BootCache::class)->delete();
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonAutoLoader::class)->register(app());

    $this->seed(RolesPermissionsSeeder::class);
    $this->admin = createAdminUser();

    // Empty addons dir so on-disk bundled modules aren't discovered as rows.
    $dir = sys_get_temp_dir().'/addons-check-'.uniqid();
    File::ensureDirectoryExists($dir);
    Config::set('addons.paths.base', $dir);

    Addon::query()->delete();
    Addon::factory()->create([
        'name'        => 'vmsacars',
        'registry_id' => 'phpvms/acars',
        'version'     => '2.1.0',
        'enabled'     => true,
    ]);
});

/**
 * Catalog with acars at 2.2.0 (one minor ahead of the installed 2.1.0). Set
 * per-test, never in beforeEach: Http::fake() appends stubs (first match wins),
 * so a beforeEach catch-all would shadow a per-test override (the 503 below).
 */
function fakeAcarsCatalog(): void
{
    Http::fake(['*' => Http::response(['data' => [[
        'registryId' => 'phpvms/acars',
        'name'       => 'vmsACARS',
        'versions'   => ['php' => '8.0', 'phpvms' => '1.0'],
        'version'    => '2.2.0',
    ]]], 200)]);
}

function adminNotificationCount(int $userId): int
{
    return DB::table('notifications')->where('notifiable_id', $userId)->count();
}

it('detects a newer catalog version and notifies the admin', function (): void {
    fakeAcarsCatalog();

    $this->artisan('addons:check-updates')
        ->expectsOutputToContain('1 addon update(s) available.')
        ->assertSuccessful();

    expect(adminNotificationCount($this->admin->id))->toBe(1);
});

it('does not re-notify for a version already notified', function (): void {
    fakeAcarsCatalog();

    $this->artisan('addons:check-updates')->assertSuccessful();
    $this->artisan('addons:check-updates')->assertSuccessful();

    expect(adminNotificationCount($this->admin->id))->toBe(1);
});

it('reports no updates when the installed version matches the catalog', function (): void {
    fakeAcarsCatalog();
    Addon::query()->update(['version' => '2.2.0']);

    $this->artisan('addons:check-updates')
        ->expectsOutputToContain('0 addon update(s) available.')
        ->assertSuccessful();

    expect(adminNotificationCount($this->admin->id))->toBe(0);
});

it('fails without notifying when the registry is unreachable and no catalog is cached', function (): void {
    Http::fake(['*' => Http::response('', 503)]);

    $this->artisan('addons:check-updates')->assertFailed();

    expect(adminNotificationCount($this->admin->id))->toBe(0);
});

it('shows a nav badge count that clears once updated', function (): void {
    fakeAcarsCatalog();
    // The badge reads cache-only; populate it once (as the page or cron would).
    app(RegistryClient::class)->refresh();

    expect(AddonsPage::getNavigationBadge())->toBe('1');

    // Bumping the installed version to the catalog's clears the badge without a
    // re-fetch: the cached catalog is unchanged, the addon is just no longer behind.
    Addon::query()->update(['version' => '2.2.0']);

    expect(AddonsPage::getNavigationBadge())->toBeNull();
});
