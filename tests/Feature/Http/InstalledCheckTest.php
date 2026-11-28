<?php

declare(strict_types=1);

use App\Exceptions\SiteUnavailable;
use App\Filament\Setup\WebInstaller;
use App\Http\Middleware\InstalledCheck;
use App\Http\Middleware\SetupGate;
use App\Models\User;
use App\Support\InstallMarker;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    // TestCase turns this off suite-wide; this file covers it.
    $this->withMiddleware(InstalledCheck::class);
});

it('answers 503 instead of redirecting when the database is unreachable', function (): void {
    // RefreshDatabase rolls back on the default connection at teardown, so the
    // original default has to be restored before the test ends.
    $original = config('database.default');

    config([
        'database.connections.unreachable' => [
            'driver'   => 'pgsql',
            'host'     => '127.0.0.1',
            'port'     => 1,
            'database' => 'x',
            'username' => 'x',
            'password' => 'x',
        ],
        'database.default' => 'unreachable',
    ]);
    DB::purge();

    try {
        $response = $this->get('/');
    } finally {
        config(['database.default' => $original]);
        DB::purge('unreachable');
    }

    $response->assertStatus(503)->assertHeaderMissing('Location');
});

it('answers 503 instead of redirecting when there are no users', function (): void {
    $this->get('/')->assertStatus(503)->assertHeaderMissing('Location');
});

it('answers 503 on an admin panel page when the database is unreachable', function (): void {
    $this->withMiddleware(SetupGate::class);
    app(InstallMarker::class)->write();
    $original = config('database.default');

    config([
        'database.connections.unreachable' => [
            'driver'   => 'pgsql',
            'host'     => '127.0.0.1',
            'port'     => 1,
            'database' => 'x',
            'username' => 'x',
            'password' => 'x',
        ],
        'database.default' => 'unreachable',
    ]);
    DB::purge();

    try {
        $response = $this->get('/admin/login');
    } finally {
        config(['database.default' => $original]);
        DB::purge('unreachable');
    }

    $response->assertStatus(503);
});

it('detects connection failures but not other query errors', function (): void {
    $connection = new QueryException(
        'pgsql',
        'select 1',
        [],
        new PDOException('connection to server failed')
    );
    $connection->errorInfo = ['08006', 7, 'connection failure'];

    $missingTable = new QueryException(
        'pgsql',
        'select * from nope',
        [],
        new PDOException('relation "nope" does not exist')
    );
    $missingTable->errorInfo = ['42P01', 7, 'undefined table'];

    $bindingLooksLikeOutage = new QueryException(
        'pgsql',
        'insert into notes (body) values (?)',
        ['server has gone away'],
        new PDOException('null value in column "title"')
    );
    $bindingLooksLikeOutage->errorInfo = ['23502', 7, 'not null violation'];

    expect(SiteUnavailable::causedBy($connection))->toBeTrue()
        ->and(SiteUnavailable::causedBy(new RuntimeException('wrapped', 0, $connection)))->toBeTrue()
        ->and(SiteUnavailable::causedBy($missingTable))->toBeFalse()
        ->and(SiteUnavailable::causedBy(new RuntimeException('No such file or directory')))->toBeFalse()
        ->and(SiteUnavailable::causedBy($bindingLooksLikeOutage))->toBeFalse()
        ->and(SiteUnavailable::causedBy(new SQLiteDatabaseDoesNotExistException('/x.sqlite')))->toBeTrue();
});

it('skips the database once the install marker is trusted', function (): void {
    $marker = app(InstallMarker::class);
    $marker->write();

    app()->instance('env', 'production');

    $userQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$userQueries): void {
        if (str_contains($query->sql, 'users')) {
            $userQueries++;
        }
    });

    $response = $this->get('/login');

    expect($response->status())->not->toBe(503)
        ->and($userQueries)->toBe(0);
});

it('still checks the database behind the marker in a verified environment', function (): void {
    $marker = app(InstallMarker::class);
    $marker->write();

    app()->instance('env', 'local');

    $this->get('/')->assertStatus(503);
});

it('writes the marker for a site installed before it existed', function (): void {
    User::factory()->create();
    $marker = app(InstallMarker::class);

    expect($marker->exists())->toBeFalse();

    $this->get('/login');

    expect($marker->exists())->toBeTrue();
});

it('keeps the installer closed during an outage behind an advisory marker', function (): void {
    app(InstallMarker::class)->write();
    app()->instance('env', 'local');

    $original = config('database.default');
    config([
        'database.connections.unreachable' => [
            'driver'   => 'pgsql',
            'host'     => '127.0.0.1',
            'port'     => 1,
            'database' => 'x',
            'username' => 'x',
            'password' => 'x',
        ],
        'database.default' => 'unreachable',
    ]);
    DB::purge();

    try {
        $closed = !WebInstaller::canAccess();
    } finally {
        config(['database.default' => $original]);
        DB::purge('unreachable');
    }

    expect($closed)->toBeTrue();
});

it('links the not-set-up page to the admin panel', function (): void {
    $this->get('/')->assertStatus(503)->assertSee('href="/admin"', false);
});

it('answers the API with JSON 503 before setup', function (): void {
    $this->getJson('/api/airports/hubs')
        ->assertStatus(503)
        ->assertJsonPath('error.status', 503);
});

it('answers the API with JSON 503 when the database is unreachable', function (): void {
    $original = config('database.default');

    config([
        'database.connections.unreachable' => [
            'driver'   => 'pgsql',
            'host'     => '127.0.0.1',
            'port'     => 1,
            'database' => 'x',
            'username' => 'x',
            'password' => 'x',
        ],
        'database.default' => 'unreachable',
    ]);
    DB::purge();

    try {
        $response = $this->getJson('/api/airports/hubs');
    } finally {
        config(['database.default' => $original]);
        DB::purge('unreachable');
    }

    $response->assertStatus(503)->assertJsonPath('error.status', 503);
});

it('lets the API through once a user exists', function (): void {
    User::factory()->create();

    expect($this->getJson('/api/airports/hubs')->status())->not->toBe(503);
});

it('answers 503 behind a trusted marker while setup is incomplete', function (): void {
    app(InstallMarker::class)->write();
    app()->instance('env', 'production');
    updateSetting('general.setup_completed', false);

    $this->get('/login')->assertStatus(503);

    updateSetting('general.setup_completed', true);

    expect($this->get('/login')->status())->not->toBe(503);
});
