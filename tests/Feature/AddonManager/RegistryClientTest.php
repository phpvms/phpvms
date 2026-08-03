<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Support\BootCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\AddonManager\Services\RegistryClient;

// The addon-manager module lives at modules/phpvms-addon-manager/app; its
// namespace only autoloads after the boot cache is primed and AddonAutoLoader
// registers the PSR-4 mapping (mirrors SampleModuleHelpersTest).
beforeEach(function (): void {
    app(BootCache::class)->delete();
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonAutoLoader::class)->register(app());

    config([
        'addon-manager.registry_url' => 'https://registry.test',
        'addon-manager.catalog_ttl'  => 3600,
        'addon-manager.http_timeout' => 20,
    ]);

    Cache::flush();
});

function packagesResponse(array $overrides = []): array
{
    return ['data' => [array_merge([
        'registry_id' => 'phpvms/acars',
        'name'        => 'vmsACARS',
        'description' => 'Full ACARS suite',
        'category'    => 'ACARS',
        'license'     => 'MIT',
        'publisher'   => 'phpVMS',
        'versions'    => ['php' => '8.4', 'phpvms' => '7.0'],
        'version'     => '2.2.0',
    ], $overrides)]];
}

it('fetches and caches the catalog keyed by registry_id', function (): void {
    Http::fake(['registry.test/v1/packages' => Http::response(packagesResponse(), 200)]);

    $catalog = app(RegistryClient::class)->catalog();

    expect($catalog['entries'])->toHaveKey('phpvms/acars');
    expect($catalog['entries']['phpvms/acars']['name'])->toBe('vmsACARS');
    expect($catalog['stale'])->toBeFalse();
    expect($catalog['synced_at'])->not->toBeNull();

    // Second call within TTL hits cache — no second request.
    app(RegistryClient::class)->catalog();
    Http::assertSentCount(1);
});

it('re-fetches on manual refresh', function (): void {
    Http::fake(['registry.test/v1/packages' => Http::response(packagesResponse(), 200)]);

    app(RegistryClient::class)->catalog();
    app(RegistryClient::class)->refresh();

    Http::assertSentCount(2);
});

it('serves the stale catalog when a refresh fails', function (): void {
    Http::fake(['registry.test/v1/packages' => Http::sequence()
        ->push(packagesResponse(), 200)
        ->push('', 500)]);

    app(RegistryClient::class)->catalog();
    $stale = app(RegistryClient::class)->refresh();

    expect($stale['entries'])->toHaveKey('phpvms/acars');
    expect($stale['stale'])->toBeTrue();
    expect($stale['error'])->toBeNull();
});

it('reports an error state when there is no cache and the fetch fails', function (): void {
    Http::fake(['registry.test/v1/packages' => Http::response('', 503)]);

    $catalog = app(RegistryClient::class)->catalog();

    expect($catalog['entries'])->toBe([]);
    expect($catalog['error'])->not->toBeNull();
});

it('tolerates a flat name, snake_case repo url, and constraint version mins', function (): void {
    Http::fake(['registry.test/v1/packages' => Http::response(['data' => [[
        'name'           => 'acars',
        'description'    => 'ACARS',
        'category'       => 'operations',
        'license'        => 'MIT',
        'repository_url' => 'https://github.com/x/acars',
        'versions'       => ['php' => '>=8.4', 'phpvms' => '>=8.0'],
        'version'        => '',
    ]]], 200)]);

    $catalog = app(RegistryClient::class)->catalog();

    // Keyed by the flat name when registryId is absent.
    expect($catalog['entries'])->toHaveKey('acars');
    $entry = $catalog['entries']['acars'];
    expect($entry['repository_url'])->toBe('https://github.com/x/acars')
        ->and($entry['min_php'])->toBe('8.4')
        ->and($entry['min_phpvms'])->toBe('8.0');
});

it('tolerates missing icon, screenshots, and stats fields', function (): void {
    Http::fake(['registry.test/v1/packages' => Http::response(packagesResponse(), 200)]);

    $entry = app(RegistryClient::class)->catalog()['entries']['phpvms/acars'];

    expect($entry['icon'])->toBeNull();
    expect($entry['screenshots'])->toBe([]);
    expect($entry['installs_total'])->toBe(0);
});
