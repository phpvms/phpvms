<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Support\BootCache;
use App\Models\Addon;
use App\Services\CronService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\AddonManager\Filament\Pages\Addons as AddonsPage;
use Modules\AddonManager\Jobs\InstallAddonJob;

/**
 * A three-package catalog: one installed with an update (acars), one installable
 * (skybank), one incompatible (weatherdeck, needs phpvms 999).
 *
 * @return array<int, array<string, mixed>>
 */
function fakeDefaultCatalog(): void
{
    // A single stub per test: Http::fake() appends (first match wins), so setting
    // one here and another in a test would let this one shadow the override.
    Http::fake(['*' => Http::response(['data' => fakeCatalogPackages()], 200)]);
}

/**
 * @return array<int, array<string, mixed>>
 */
function fakeCatalogPackages(): array
{
    return [
        [
            'registryId'    => 'phpvms/acars',
            'name'          => 'vmsACARS',
            'description'   => 'Full ACARS suite with live tracking.',
            'category'      => 'ACARS',
            'license'       => 'MIT',
            'publisher'     => 'phpvms',
            'repositoryUrl' => 'https://github.com/phpvms/acars',
            'versions'      => ['php' => '8.0', 'phpvms' => '1.0'],
            'version'       => '2.2.0',
            'stats'         => ['installs_total' => 1200],
        ],
        [
            'registryId'  => 'skyops/skybank',
            'name'        => 'SkyBank Finance',
            'description' => 'Pilot bank accounts and ledger.',
            'category'    => 'Finance',
            'license'     => 'MIT',
            'publisher'   => 'skyops',
            'versions'    => ['php' => '8.0', 'phpvms' => '1.0'],
            'version'     => '1.0.3',
            'stats'       => ['installs_total' => 412],
        ],
        [
            'registryId'  => 'weather/deck',
            'name'        => 'WeatherDeck',
            'description' => 'Live METAR overlays.',
            'category'    => 'Integrations',
            'versions'    => ['php' => '8.0', 'phpvms' => '999.0'],
            'version'     => '0.9.1',
        ],
    ];
}

beforeEach(function (): void {
    // The registry catalog is cached forever; flush so each test's Http::fake
    // catalog is the one served (otherwise the first test's payload sticks).
    Cache::flush();

    app(BootCache::class)->delete();
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonAutoLoader::class)->register(app());

    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    // Scan an empty addons dir so on-disk bundled modules aren't re-detected as
    // installed rows and pollute the counts.
    $this->modules = sys_get_temp_dir().'/addons-page-'.uniqid();
    File::ensureDirectoryExists($this->modules);
    Config::set('addons.paths.base', $this->modules);

    // Only acars is installed (at 2.1.0 — one minor behind the catalog's 2.2.0).
    Addon::query()->delete();
    Addon::factory()->create([
        'name'        => 'vmsacars',
        'registry_id' => 'phpvms/acars',
        'version'     => '2.1.0',
        'enabled'     => true,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->modules);
    app(BootCache::class)->delete();
});

it('renders for an authorized admin and lists the catalog', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->assertSuccessful()
        ->assertSee('vmsACARS')
        ->assertSee('SkyBank Finance')
        ->assertSee('WeatherDeck');
});

it('counts browse, updates and installed tabs', function (): void {
    fakeDefaultCatalog();

    $counts = Livewire::test(AddonsPage::class)->instance()->tabCounts();

    expect($counts)->toBe([
        'browse'    => 3,
        'updates'   => 1,
        'installed' => 1,
    ]);
});

it('filters the Updates tab to addons with a newer catalog version', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'updates')
        ->assertSee('vmsACARS')
        ->assertDontSee('SkyBank Finance')
        ->assertDontSee('WeatherDeck');
});

it('searches the listing by name', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('search', 'skybank')
        ->assertSee('SkyBank Finance')
        ->assertDontSee('WeatherDeck');
});

it('filters the listing by category', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('category', 'Finance')
        ->assertSee('SkyBank Finance')
        ->assertDontSee('vmsACARS');
});

it('disables the install action for an incompatible addon', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->call('select', 'weather/deck')
        ->assertActionDisabled('install');
});

it('labels the primary action "Update to vX" for an installed addon with a newer version', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->call('select', 'phpvms/acars')
        ->assertSee('Update to v2.2.0');
});

it('disables install for a catalog entry that carries no installable version', function (): void {
    Http::fake(['*' => Http::response(['data' => [[
        'registryId' => 'empty/pkg',
        'name'       => 'EmptyPkg',
        'versions'   => ['php' => '8.0', 'phpvms' => '1.0'],
        'version'    => '',
    ]]], 200)]);

    Livewire::test(AddonsPage::class)
        ->call('select', 'empty/pkg')
        ->assertActionDisabled('install');
});

it('disables an addon reactively without a page redirect', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->call('disable', 'vmsacars')
        ->assertNoRedirect();

    expect(Addon::query()->where('registry_id', 'phpvms/acars')->value('enabled'))->toBeFalsy();
});

it('hides disable and delete for a bundled installed addon', function (): void {
    fakeDefaultCatalog();
    Addon::query()->where('registry_id', 'phpvms/acars')->update(['bundled' => true]);

    Livewire::test(AddonsPage::class)
        ->call('select', 'phpvms/acars')
        ->assertSee('bundled with phpVMS')
        ->assertDontSee('>Disable<');
});

it('scrubs a non-http repository url from an untrusted catalog entry', function (): void {
    Http::fake(['*' => Http::response(['data' => [[
        'registryId'    => 'evil/pkg',
        'name'          => 'EvilPkg',
        'repositoryUrl' => 'javascript:alert(document.cookie)',
        'versions'      => ['php' => '8.0', 'phpvms' => '1.0'],
        'version'       => '1.0.0',
    ]]], 200)]);

    $row = Livewire::test(AddonsPage::class)
        ->call('select', 'evil/pkg')
        ->instance()
        ->selected();

    expect($row['repository_url'])->toBe('');
});

it('dispatches the install job when a queue worker will process it', function (): void {
    fakeDefaultCatalog();

    Config::set('queue.default', 'database');
    Config::set('phpvms.run_queued_jobs_in_cron', true);
    $this->mock(CronService::class, function ($mock): void {
        $mock->shouldReceive('cronProblemExists')->andReturnFalse();
    });
    Bus::fake();

    Livewire::test(AddonsPage::class)
        ->call('select', 'skyops/skybank')
        ->callAction('install', ['run_migrations' => true]);

    Bus::assertDispatched(
        InstallAddonJob::class,
        fn (InstallAddonJob $job): bool => $job->registryId === 'skyops/skybank'
            && $job->version === '1.0.3'
            && $job->runMigrations,
    );
});
