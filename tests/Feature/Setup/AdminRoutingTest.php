<?php

declare(strict_types=1);

use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Support\CachedPendingWork;
use App\Features\Setup\Support\CoreSeeds;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\Pages\SystemUpdates;
use App\Http\Middleware\AdminGate;
use App\Http\Middleware\InstalledCheck;
use App\Http\Middleware\SetupGate;
use App\Models\User;
use App\Support\InstallMarker;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

beforeEach(function (): void {
    // TestCase turns these off suite-wide; this file covers them.
    $this->withMiddleware([SetupGate::class, AdminGate::class]);

    $this->dir = sys_get_temp_dir().'/phpvms-admin-routing-'.uniqid();
    mkdir($this->dir);
    $this->runState = new SetupRunState($this->dir.'/state.json');
    $this->completed = new UpgradeCompleted($this->dir.'/completed', databaseSignal: false);
    $this->paths = new class() extends MigrationPaths
    {
        /** @var list<string> */
        public array $pending = [];

        public function pendingSchema(): array
        {
            return $this->pending;
        }

        public function pendingData(): array
        {
            return [];
        }
    };
    $detect = new DetectPendingWork($this->paths, app(CoreSeeds::class), $this->runState);
    $this->instance(SetupRunState::class, $this->runState);
    $this->instance(UpgradeCompleted::class, $this->completed);
    $this->instance(CachedPendingWork::class, new CachedPendingWork($detect, $this->dir.'/pending.json'));

    $this->defaultConnection = config('database.default');
    DB::table('users')->delete();
});

afterEach(function (): void {
    config(['database.default' => $this->defaultConnection]);
    DB::purge('broken');
    Artisan::call('up');
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

dataset('panel entry points', ['/admin', '/admin/login']);

test('a fresh site is sent to the installer', function (string $path): void {
    $this->get($path)->assertRedirect('/admin/install');
})->with('panel entry points');

test('a fresh site with a finished run is sent to the first user form', function (string $path): void {
    $this->completed->write();

    $this->get($path)->assertRedirect('/admin/first-user');
})->with('panel entry points');

test('a fresh site serves the installer page through the full stack', function (): void {
    $this->withMiddleware(InstalledCheck::class);

    $this->get('/admin/install')->assertOk();
});

test('a fresh site with a finished run serves the first user form through the full stack', function (): void {
    $this->withMiddleware(InstalledCheck::class);
    $this->completed->write();

    $this->get('/admin/first-user')->assertOk();
});

test('a v7 site is sent to the upgrader', function (string $path): void {
    User::factory()->create();
    $this->paths->pending = ['2026_01_01_000000_pending'];

    $this->get($path)->assertRedirect('/system/upgrade');
})->with('panel entry points');

test('an admin on an 8.x site with pending work is sent to the update page', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->completed->write();
    $this->paths->pending = ['2026_01_01_000000_pending'];
    $this->actingAs(createAdminUser());

    $this->get('/admin')->assertRedirect(SystemUpdates::getUrl(panel: 'admin'));
});

test('a guest on an 8.x site with pending work gets the login page and nothing is queued', function (): void {
    User::factory()->create();
    $this->completed->write();
    $this->paths->pending = ['2026_01_01_000000_pending'];

    $this->get('/admin')->assertRedirectContains('login');
    $this->get('/admin/login')->assertOk();

    expect($this->runState->read())->toBeNull();
});

test('a current site loads the panel', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->completed->write();
    $this->actingAs(createAdminUser());

    $this->get('/admin')->assertOk();
});

test('an unreachable database with the install marker answers 503 and offers no installer', function (): void {
    $marker = new InstallMarker($this->dir.'/installed');
    $marker->write();
    $this->instance(InstallMarker::class, $marker);
    config(['database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/phpvms.sqlite', 'prefix' => '']]);
    config(['database.default' => 'broken']);
    DB::purge('broken');

    $this->get('/admin')->assertStatus(503)->assertHeaderMissing('Location');
});

/** @return array{snapshot: string, updates: array<mixed>, calls: array<mixed>} */
function routingPageComponent(string $html): array
{
    preg_match('/wire:snapshot="([^"]+)"/', $html, $match);

    return ['snapshot' => htmlspecialchars_decode($match[1], ENT_QUOTES), 'updates' => [], 'calls' => []];
}

test('while down, admin pages and their Livewire updates pass and public pages answer 503', function (): void {
    User::factory()->create();
    $this->completed->write();

    // A signed snapshot of the login page, opened before the site went down.
    $login = routingPageComponent($this->get('/admin/login')->assertOk()->getContent());
    Artisan::call('down');
    Livewire::flushState();

    $adminUpdate = $this->postJson(EndpointResolver::updatePath(), ['components' => [$login]], ['X-Livewire' => 'true']);
    $publicSnapshot = json_encode(['memo' => ['path' => 'flights', 'name' => 'x']]);
    $publicUpdate = $this->postJson(EndpointResolver::updatePath(), ['components' => [['snapshot' => $publicSnapshot, 'updates' => [], 'calls' => []]]]);

    expect($this->get('/admin/login')->getStatusCode())->toBe(200)
        ->and($adminUpdate->getStatusCode())->toBe(200)
        ->and($adminUpdate->json('components.0.snapshot'))->toBeString()
        ->and($this->get('/')->getStatusCode())->toBe(503)
        ->and($publicUpdate->getStatusCode())->toBe(503);
});

test('while down, the plain web routes under admin/ and paths that only start with admin answer 503', function (): void {
    User::factory()->create();
    $this->completed->write();
    Artisan::call('down');

    $snapshot = json_encode(['memo' => ['path' => 'admin/pages/1/preview', 'name' => 'x']]);

    expect($this->postJson('/admin/route-forge/api/lint')->getStatusCode())->toBe(503)
        ->and($this->get('/admin/pages/1/preview')->getStatusCode())->toBe(503)
        ->and($this->get('/administration')->getStatusCode())->toBe(503)
        ->and($this->postJson(EndpointResolver::updatePath(), ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]])->getStatusCode())->toBe(503);
});

test('the API answers not installed as JSON before setup', function (): void {
    $this->withMiddleware(InstalledCheck::class);

    $this->getJson('/api/airports/hubs')
        ->assertStatus(503)
        ->assertJsonPath('error.status', 503);
});

test('a fresh site keeps its session and CSRF token across a page load and a Livewire update', function (): void {
    $this->withMiddleware(InstalledCheck::class);
    config(['session.driver' => 'array', 'session.files' => $this->dir]);

    $page = $this->get('/admin/install')->assertOk();
    preg_match('/<meta name="csrf-token" content="([^"]+)"/', $page->getContent(), $token);
    $cookie = collect($page->headers->getCookies())->first(fn ($c): bool => $c->getName() === config('session.cookie'));

    expect(config('session.driver'))->toBe('file')
        ->and($cookie)->not->toBeNull()
        ->and($token)->not->toBeEmpty();

    // A real second request: the session cookie from the first, as a browser sends it.
    Livewire::flushState();
    config(['session.driver' => 'array']);

    $update = $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
        ->postJson(EndpointResolver::updatePath(), ['components' => [routingPageComponent($page->getContent())]], ['X-Livewire' => 'true', 'X-CSRF-TOKEN' => $token[1]])
        ->assertOk();

    expect(config('session.driver'))->toBe('file')
        ->and($update->json('components.0.snapshot'))->toBeString()
        ->and(session()->token())->toBe($token[1]);
});

test('a fresh site with an empty database serves the installer on database sessions', function (): void {
    $this->withMiddleware(InstalledCheck::class);
    $database = storage_path('framework/testing/phpvms-empty-'.uniqid().'.sqlite');
    touch($database);

    try {
        config([
            'database.connections.empty' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => false],
            'database.default'           => 'empty',
            'session.driver'             => 'database',
            'session.connection'         => null,
            // The provider swaps the cache to array at registration while the site is fresh, which this process cannot redo.
            'cache.default' => 'array',
        ]);
        DB::purge('empty');
        $this->app->forgetInstance(CachedPendingWork::class);
        $this->app->forgetInstance(DetectPendingWork::class);

        $this->get('/admin/install')->assertOk();
    } finally {
        DB::purge('empty');
        config(['database.default' => $this->defaultConnection]);
        @unlink($database);
    }
});

test('an installed site keeps its configured session driver', function (): void {
    User::factory()->create();
    $this->completed->write();
    config(['session.driver' => 'array']);

    $this->get('/admin/login');
    $this->postJson(EndpointResolver::updatePath(), ['components' => []]);

    expect(config('session.driver'))->toBe('array');
});

test('the plain web routes under admin/ are not routed to the installer on a fresh site', function (): void {
    $response = $this->postJson('/admin/route-forge/api/lint');

    expect($response->isRedirect())->toBeFalse();
});
