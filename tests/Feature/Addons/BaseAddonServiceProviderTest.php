<?php

declare(strict_types=1);

use App\Features\Hooks\Exceptions\HookDenied;
use App\Features\Hooks\Facades\Hooks;
use App\Models\Addon;
use App\Models\Role;
use App\Models\User;
use Composer\Autoload\ClassLoader;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PhpvmsAddonFixture\Events\AcmeFixtureEvent;
use PhpvmsAddonFixture\Hooks\AcmeGate;
use PhpvmsAddonFixture\Hooks\AcmeGateListener;
use PhpvmsAddonFixture\Listeners\AcmeListener;
use PhpvmsAddonFixture\Providers\AcmeServiceProvider;
use PhpvmsBadHooksFixture\Listeners\MissingPointListener;
use PhpvmsBadHooksFixture\Providers\BadHooksServiceProvider;

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
        if (is_array($entry) && $entry[0] instanceof ClassLoader) {
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

it('registers a gate hook listener with the dispatcher, with its priority and addon', function (): void {
    expect(Hooks::listenersFor(AcmeGate::class))->toBe([[
        'listener' => AcmeGateListener::class,
        'addon'    => 'acme',
        'priority' => 40,
        'sequence' => 0,
    ]])
        ->and(Event::getListeners(AcmeGate::class))->toBe([]);

    expect(fn () => Hooks::gate(new AcmeGate()))->toThrow(HookDenied::class, 'Acme says no');
});

function registerBadHooksPsr4(): void
{
    $loader = collect(spl_autoload_functions())
        ->first(fn ($entry): bool => is_array($entry) && $entry[0] instanceof ClassLoader)[0];
    $loader->addPsr4('PhpvmsBadHooksFixture\\', realpath(__DIR__.'/../../fixtures/Addons/badhooks/app'));
}

it('quarantines an addon whose listener targets a hook point that does not exist', function (): void {
    registerBadHooksPsr4();
    Notification::fake();
    Log::spy();
    $addon = Addon::factory()->create(['name' => 'BadHooks', 'namespace' => 'PhpvmsBadHooksFixture', 'enabled' => true]);
    $admin = User::factory()->create();
    $admin->assignRole(Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']));

    app()->register(BadHooksServiceProvider::class);

    expect($addon->refresh()->enabled)->toBeFalse();
    Notification::assertSentTo($admin, DatabaseNotification::class, function (DatabaseNotification $notification) use ($admin): bool {
        $data = $notification->toDatabase($admin);

        return str_contains((string) $data['title'], 'BadHooks')
            && str_contains((string) $data['body'], MissingPointListener::class)
            && $data['status'] === 'danger';
    });
    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'Addon "badhooks" quarantined') && str_contains($message, MissingPointListener::class))
        ->once();
});

it('boots and only logs when the broken addon has no row to disable', function (): void {
    registerBadHooksPsr4();
    Notification::fake();
    Log::spy();

    app()->register(BadHooksServiceProvider::class);

    Notification::assertNothingSent();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'quarantined'))->once();
});

it('skips the listener and keeps a bundled addon enabled', function (): void {
    registerBadHooksPsr4();
    Notification::fake();
    Log::spy();
    $addon = Addon::factory()->create(['name' => 'BadHooks', 'namespace' => 'PhpvmsBadHooksFixture', 'enabled' => true, 'bundled' => true]);

    app()->register(BadHooksServiceProvider::class);

    expect($addon->refresh()->enabled)->toBeTrue();
    Notification::assertNothingSent();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'bundled'))->once();
});
