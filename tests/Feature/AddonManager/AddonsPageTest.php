<?php

declare(strict_types=1);

use App\Features\Addons\AddonAutoLoader;
use App\Features\Addons\Support\BootCache;
use App\Models\Addon;
use App\Services\CronService;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
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
    Config::set('addons.paths.root', $this->modules);

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

it('lists the catalog and local addons on the All tab', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'all')
        ->assertSuccessful()
        ->assertCanSeeTableRecords(['phpvms/acars', 'skyops/skybank', 'weather/deck']);
});

it('counts the all, installed, updates and not installed tabs', function (): void {
    fakeDefaultCatalog();

    $counts = addonsPageInstance(Livewire::test(AddonsPage::class))->tabCounts();

    expect($counts)->toBe([
        'all'       => 3,
        'installed' => 1,
        'updates'   => 1,
        'available' => 2,
    ]);
});

it('filters the Updates tab to addons with a newer catalog version', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'updates')
        ->assertCanSeeTableRecords(['phpvms/acars'])
        ->assertCanNotSeeTableRecords(['skyops/skybank', 'weather/deck']);
});

it('lists only catalog addons that are not installed on the Not installed tab', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'available')
        ->assertCanSeeTableRecords(['skyops/skybank', 'weather/deck'])
        ->assertCanNotSeeTableRecords(['phpvms/acars']);
});

it('searches the listing by name and publisher', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'all')
        ->searchTable('skybank')
        ->assertCanSeeTableRecords(['skyops/skybank'])
        ->assertCanNotSeeTableRecords(['weather/deck', 'phpvms/acars'])
        ->searchTable('weather')
        ->assertCanSeeTableRecords(['weather/deck'])
        ->assertCanNotSeeTableRecords(['skyops/skybank']);
});

it('filters the listing by category', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'all')
        ->filterTable('category', 'Finance')
        ->assertCanSeeTableRecords(['skyops/skybank'])
        ->assertCanNotSeeTableRecords(['phpvms/acars']);
});

it('drives the table filters from the filter bar', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'all')
        ->call('setCategoryFilter', 'Finance')
        ->assertCanSeeTableRecords(['skyops/skybank'])
        ->assertCanNotSeeTableRecords(['phpvms/acars'])
        ->call('setCategoryFilter', 'Not a category')
        ->assertCanSeeTableRecords(['phpvms/acars', 'skyops/skybank'])
        ->call('toggleFilterFlag', 'hide_untested')
        ->assertCanNotSeeTableRecords(['weather/deck'])
        ->call('toggleFilterFlag', 'hide_untested')
        ->assertCanSeeTableRecords(['weather/deck']);
});

it('hides untested addons that are not installed', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'all')
        ->filterTable('hide_untested', true)
        ->assertCanSeeTableRecords(['phpvms/acars', 'skyops/skybank'])
        ->assertCanNotSeeTableRecords(['weather/deck']);
});

it('disables the install action for an incompatible addon', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'available')
        ->assertActionDisabled(TestAction::make('install')->table('weather/deck'));
});

it('labels the primary action "Update to vX" for an installed addon with a newer version', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->assertActionHasLabel(TestAction::make('install')->table('phpvms/acars'), 'Update to v2.2.0');
});

it('disables install for a catalog entry that carries no installable version', function (): void {
    Http::fake(['*' => Http::response(['data' => [[
        'registryId' => 'empty/pkg',
        'name'       => 'EmptyPkg',
        'versions'   => ['php' => '8.0', 'phpvms' => '1.0'],
        'version'    => '',
    ]]], 200)]);

    Livewire::test(AddonsPage::class)
        ->set('activeTab', 'available')
        ->assertActionDisabled(TestAction::make('install')->table('empty/pkg'));
});

it('disables an addon reactively without a page redirect', function (): void {
    fakeDefaultCatalog();
    // Up to date, so Disable is the card's primary action rather than a menu item.
    Addon::query()->where('registry_id', 'phpvms/acars')->update(['version' => '2.2.0']);

    Livewire::test(AddonsPage::class)
        ->callAction(TestAction::make('disable')->table('phpvms/acars'))
        ->assertNoRedirect();

    expect(Addon::query()->where('registry_id', 'phpvms/acars')->value('enabled'))->toBeFalsy();
});

it('keeps enable and disable in the menu while an update is the primary action', function (): void {
    fakeDefaultCatalog();

    Livewire::test(AddonsPage::class)
        ->assertActionHidden(TestAction::make('disable')->table('phpvms/acars'))
        ->callAction(TestAction::make('disableFromMenu')->table('phpvms/acars'));

    expect(Addon::query()->where('registry_id', 'phpvms/acars')->value('enabled'))->toBeFalsy();
});

it('cannot disable or delete a bundled installed addon', function (): void {
    fakeDefaultCatalog();
    Addon::query()->where('registry_id', 'phpvms/acars')->update(['bundled' => true]);

    Livewire::test(AddonsPage::class)
        ->assertActionHidden(TestAction::make('delete')->table('phpvms/acars'))
        ->assertActionHidden(TestAction::make('disable')->table('phpvms/acars'))
        ->assertActionHidden(TestAction::make('disableFromMenu')->table('phpvms/acars'));
});

it('scrubs a non-http repository url from an untrusted catalog entry', function (): void {
    Http::fake(['*' => Http::response(['data' => [[
        'registryId'    => 'evil/pkg',
        'name'          => 'EvilPkg',
        'repositoryUrl' => 'javascript:alert(document.cookie)',
        'versions'      => ['php' => '8.0', 'phpvms' => '1.0'],
        'version'       => '1.0.0',
    ]]], 200)]);

    $row = addonsPageInstance(Livewire::test(AddonsPage::class))
        ->allEntries()
        ->firstWhere('id', 'evil/pkg');

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
        ->set('activeTab', 'available')
        ->callAction(TestAction::make('install')->table('skyops/skybank'), ['run_migrations' => true]);

    Bus::assertDispatched(
        InstallAddonJob::class,
        fn (InstallAddonJob $job): bool => $job->registryId === 'skyops/skybank'
            && $job->version === '1.0.3'
            && $job->runMigrations,
    );
});

it('orders the cards A to Z by name, ignoring case and the official flag', function (): void {
    Http::fake(['*' => Http::response(['data' => [
        ['registryId' => 'phpvms/acars', 'name' => 'vmsACARS', 'official' => true, 'versions' => ['php' => '8.0', 'phpvms' => '1.0'], 'version' => '2.2.0'],
        ['registryId' => 'b/upper', 'name' => 'SkyBank Finance', 'versions' => ['php' => '8.0', 'phpvms' => '1.0'], 'version' => '1.0.3'],
        ['registryId' => 'c/lower', 'name' => 'bank', 'versions' => ['php' => '8.0', 'phpvms' => '1.0'], 'version' => '1.0.0'],
        ['registryId' => 'd/incompatible', 'name' => 'Alpha', 'versions' => ['php' => '8.0', 'phpvms' => '999.0'], 'version' => '1.0.0'],
    ]], 200)]);

    $page = addonsPageInstance(Livewire::test(AddonsPage::class)->set('activeTab', 'all'));

    expect($page->listing()->pluck('name')->values()->all())
        ->toBe(['Alpha', 'bank', 'SkyBank Finance', 'vmsACARS']);
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
        ->assertCanSeeTableRecords(['phpvms/acars']);
});

/**
 * Every plate gets a colour. Category first so related add-ons look related;
 * otherwise a hue off the id, because only catalog entries carry a category and
 * a locally installed add-on would be left grey.
 */
it('tints every monogram plate, by category or by id', function (): void {
    fakeDefaultCatalog();

    $rows = addonsPageInstance(Livewire::test(AddonsPage::class)->set('activeTab', 'all'))
        ->allEntries()
        ->keyBy('id');

    expect($rows->pluck('tint')->filter())->toHaveCount($rows->count());

    // Same id, same hue, every render — the plate must not flicker between pages.
    $again = addonsPageInstance(Livewire::test(AddonsPage::class)->set('activeTab', 'all'))
        ->allEntries()
        ->keyBy('id');

    expect($again['skyops/skybank']['tint'])->toBe($rows['skyops/skybank']['tint']);
});
