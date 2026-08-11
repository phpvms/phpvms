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
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\AddonManager\Filament\Pages\Addons as AddonsPage;
use Modules\AddonManager\Jobs\InstallAddonJob;
use Modules\AddonManager\Services\RegistryClient;

/**
 * A three-package catalog: one installed with an update (acars), one installable
 * (skybank), one incompatible (weatherdeck, needs phpvms 999).
 */
function fakeDefaultCatalog(): void
{
    // A single stub per test: Http::fake() appends (first match wins), so setting
    // one here and another in a test would let this one shadow the override.
    Http::fake(['*' => Http::response(['data' => fakeCatalogPackages()], 200)]);
}

/**
 * Livewire's Testable::instance() is declared as the base Component, so the
 * concrete page type -- and with it every method the page declares -- is lost.
 * Narrow it back with a real runtime check rather than an assertion the
 * analyser has to take on trust.
 */
function addonsPageInstance(Testable $testable): AddonsPage
{
    $instance = $testable->instance();

    if (!$instance instanceof AddonsPage) {
        throw new RuntimeException('Expected the Livewire component to be an AddonsPage.');
    }

    return $instance;
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
        // The page lands on Installed; the catalog lives on the registry tab.
        ->set('activeTab', 'browse')
        ->assertSuccessful()
        ->assertSee('vmsACARS')
        ->assertSee('SkyBank Finance')
        ->assertSee('WeatherDeck');
});

it('counts browse, updates and installed tabs', function (): void {
    fakeDefaultCatalog();

    $counts = addonsPageInstance(Livewire::test(AddonsPage::class))->tabCounts();

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
        ->set('activeTab', 'browse')
        ->set('search', 'skybank')
        ->assertSee('SkyBank Finance')
        ->assertDontSee('WeatherDeck');
});

it('filters the listing by category', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'browse')
        ->set('category', 'Finance')
        ->assertSee('SkyBank Finance')
        ->assertDontSee('vmsACARS');
});

it('disables the install action for an incompatible addon', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'browse')
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
        ->set('activeTab', 'browse')
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

    $row = addonsPageInstance(
        Livewire::test(AddonsPage::class)->call('select', 'evil/pkg')
    )->selected();

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
        ->set('activeTab', 'browse')
        ->call('select', 'skyops/skybank')
        ->callAction('install', ['run_migrations' => true]);

    Bus::assertDispatched(
        InstallAddonJob::class,
        fn (InstallAddonJob $job): bool => $job->registryId === 'skyops/skybank'
            && $job->version === '1.0.3'
            && $job->runMigrations,
    );
});

/**
 * Official add-ons lead the shelf. The flag is provenance only — it never
 * changes what can be installed, just what a browsing operator meets first.
 */
it('sorts official addons above the rest while browsing', function (): void {
    // skybank sorts first alphabetically; the official flag has to beat that.
    Http::fake(['*' => Http::response(['data' => [
        ['registryId' => 'skyops/skybank', 'name' => 'SkyBank Finance', 'versions' => ['php' => '8.0', 'phpvms' => '1.0'], 'version' => '1.0.3'],
        ['registryId' => 'phpvms/acars', 'name' => 'vmsACARS', 'official' => true, 'versions' => ['php' => '8.0', 'phpvms' => '1.0'], 'version' => '2.2.0'],
    ]], 200)]);

    $page = addonsPageInstance(Livewire::test(AddonsPage::class)->set('activeTab', 'browse'));

    expect($page->listing()->pluck('name')->all())->toBe(['vmsACARS', 'SkyBank Finance']);
});

it('drops the official weighting once a search is typed', function (): void {
    // Both match "a"; with the shelf weighting gone they fall back to name order,
    // so the community package leads. A lookup ranks by match, not by publisher.
    Http::fake(['*' => Http::response(['data' => [
        ['registryId' => 'skyops/skybank', 'name' => 'Bank', 'versions' => ['php' => '8.0', 'phpvms' => '1.0'], 'version' => '1.0.3'],
        ['registryId' => 'phpvms/acars', 'name' => 'Charts', 'official' => true, 'versions' => ['php' => '8.0', 'phpvms' => '1.0'], 'version' => '2.2.0'],
    ]], 200)]);

    $page = addonsPageInstance(Livewire::test(AddonsPage::class)->set('activeTab', 'browse')->set('search', 'a'));

    expect($page->listing()->pluck('name')->all())->toBe(['Bank', 'Charts']);
});

it('pages the listing ten at a time', function (): void {
    Http::fake(['*' => Http::response(['data' => collect(range(1, 14))
        ->map(fn (int $n): array => [
            'registryId' => 'vendor/pkg'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'name'       => 'Package '.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'versions'   => ['php' => '8.0', 'phpvms' => '1.0'],
            'version'    => '1.0.0',
        ])->all()], 200)]);

    $component = Livewire::test(AddonsPage::class)->set('activeTab', 'browse');
    $page = addonsPageInstance($component);

    expect($page->listing())->toHaveCount(14)
        ->and($page->paginator()->count())->toBe(10)
        ->and($page->paginator()->firstItem())->toBe(1);

    $component->set('page', 2);

    expect(addonsPageInstance($component)->paginator()->count())->toBe(4);
});

it('returns to the first page when a filter narrows the listing', function (): void {
    fakeDefaultCatalog();

    $component = Livewire::test(AddonsPage::class)
        ->set('page', 3)
        ->set('search', 'skybank');

    expect(addonsPageInstance($component)->page)->toBe(1);
});

it('splits installed addons by enable state', function (): void {
    fakeDefaultCatalog();
    Addon::query()->where('registry_id', 'phpvms/acars')->update(['enabled' => false]);

    $component = Livewire::test(AddonsPage::class)->set('activeTab', 'installed');

    expect(addonsPageInstance($component)->stateCounts())
        ->toMatchArray(['all' => 1, 'enabled' => 0, 'disabled' => 1]);

    $component->set('state', 'enabled');
    expect(addonsPageInstance($component)->listing())->toHaveCount(0);

    $component->set('state', 'disabled');
    expect(addonsPageInstance($component)->listing()->pluck('name')->all())->toBe(['vmsACARS']);
});

/**
 * Registry down and the cached catalog has aged out. The list still works off
 * that cache, so the page says so where the counts are read rather than in a
 * toast that has already gone.
 */
it('warns on the page when the catalog it is showing is stale', function (): void {
    // One fake, sequenced: Http::fake() appends and the first match wins, so a
    // second fake('*') here would be shadowed by the first and never serve 500.
    Http::fake(['*' => Http::sequence()
        ->push(['data' => fakeCatalogPackages()], 200)
        ->push('', 500)]);

    app(RegistryClient::class)->catalog();

    // Past the freshness window (and the refresh throttle), so rendering the
    // page re-fetches, fails, and serves the cached entries flagged stale.
    $this->travel((int) config('addon-manager.catalog_ttl') + 120)->seconds();

    $component = Livewire::test(AddonsPage::class);

    expect(addonsPageInstance($component)->catalogState()['stale'])->toBeTrue();

    $component
        ->assertSee(__('addon-manager::addons.showing_cached_catalog'))
        ->assertSee('vmsACARS');
});

/**
 * Every plate gets a colour. Category first so related add-ons look related;
 * otherwise a hue off the id, because only catalog entries carry a category and
 * a locally installed add-on would be left grey.
 */
it('tints every monogram plate, by category or by id', function (): void {
    fakeDefaultCatalog();

    $rows = addonsPageInstance(Livewire::test(AddonsPage::class)->set('activeTab', 'browse'))
        ->allEntries()
        ->keyBy('id');

    expect($rows->pluck('tint')->filter())->toHaveCount($rows->count());

    // Same id, same hue, every render — the plate must not flicker between pages.
    $again = addonsPageInstance(Livewire::test(AddonsPage::class)->set('activeTab', 'browse'))
        ->allEntries()
        ->keyBy('id');

    expect($again['skyops/skybank']['tint'])->toBe($rows['skyops/skybank']['tint']);
});
