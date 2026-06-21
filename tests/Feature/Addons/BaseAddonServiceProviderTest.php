<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PhpvmsAddonFixture\Events\AcmeFixtureEvent;
use PhpvmsAddonFixture\Listeners\AcmeListener;
use PhpvmsAddonFixture\Providers\AcmeServiceProvider;

// ─────────────────────────────────────────────────────────────────────────────
// Setup: register the fixture namespace and boot the fixture provider once.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Register the fixture PSR-4 namespace with the Composer ClassLoader (once
 * per process — the autoloader is global and outlives Laravel app instances).
 */
function registerFixturePsr4(): void
{
    static $psr4Registered = false;

    if ($psr4Registered) {
        return;
    }

    $fixtureRoot = realpath(__DIR__.'/../../fixtures/Addons/acme');
    $autoloadPath = $fixtureRoot.'/app';

    $loader = null;

    foreach (spl_autoload_functions() as $entry) {
        if (is_array($entry) && isset($entry[0]) && $entry[0] instanceof ClassLoader) {
            $loader = $entry[0];

            break;
        }
    }

    if (!$loader instanceof ClassLoader) {
        throw new RuntimeException('Composer ClassLoader not found in autoload stack');
    }

    $loader->addPsr4('PhpvmsAddonFixture\\', $autoloadPath);

    $psr4Registered = true;
}

/**
 * Register the fixture service provider into the current app instance.
 *
 * Must be called in beforeEach — each test gets a fresh Laravel app
 * (RefreshDatabase recreates the app), so the provider must be re-registered
 * each time. Laravel deduplicates by class, so calling this is idempotent
 * within a single app instance.
 *
 * The provider loads its routes from a deferred booted() callback and refreshes
 * the name/action lookups itself, so addon routes are resolvable here without
 * the test having to rebuild the lookup tables.
 */
function registerFixtureAddon(): void
{
    registerFixturePsr4();
    app()->register(AcmeServiceProvider::class);

    // Commands registered via $this->commands() use Artisan::starting() callbacks,
    // which only fire when the Artisan Console Application is first created. During
    // `php artisan test` the instance is already cached. Reset it so the kernel
    // recreates Artisan on the next call, firing all pending starting callbacks.
    app(ConsoleKernel::class)->setArtisan(null);
}

// Run setup before every test in this file.
beforeEach(function (): void {
    registerFixtureAddon();
    // Reset the static spy between tests.
    AcmeListener::$handled = false;
});

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────

it('merges addon config so config("acme.enabled") is true', function (): void {
    expect(config('acme.enabled'))->toBeTrue();
});

it('registers the view namespace so view("acme::hello") exists', function (): void {
    expect(view()->exists('acme::hello'))->toBeTrue();
});

it('registers the translation namespace so __("acme::messages.hi") returns "Hello"', function (): void {
    expect(__('acme::messages.hi'))->toBe('Hello');
});

it('registers the web route so Route::has("acme.fixture") is true', function (): void {
    expect(Route::has('acme.fixture'))->toBeTrue();
});

it('returns "ok" from the acme-fixture route', function (): void {
    $this->get('/acme-fixture')->assertSuccessful()->assertSeeText('ok');
});

it('registers the addon database/migrations directory with the migrator', function (): void {
    $registered = collect(app('migrator')->paths())
        ->contains(fn (string $path): bool => str_ends_with($path, 'acme'.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'));

    expect($registered)->toBeTrue();
});

it('runs the addon migration on artisan migrate so the table exists', function (): void {
    Artisan::call('migrate', ['--force' => true]);

    expect(Schema::hasTable('acme_fixture_items'))->toBeTrue();
});

it('registers the console command so Artisan::all() contains "acme:ping"', function (): void {
    // Tests run in console context, so registerCommands() fires.
    expect(Artisan::all())->toHaveKey('acme:ping');
});

it('auto-discovers AcmeListener for AcmeFixtureEvent and the listener runs on dispatch', function (): void {
    // Confirm the listener was wired by the provider.
    $listeners = Event::getListeners(AcmeFixtureEvent::class);
    expect($listeners)->not->toBeEmpty('AcmeListener should be registered for AcmeFixtureEvent');

    // Dispatch the event and verify the side-effect.
    event(new AcmeFixtureEvent());
    expect(AcmeListener::$handled)->toBeTrue();
});
