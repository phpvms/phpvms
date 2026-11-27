<?php

declare(strict_types=1);

use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Actions\ResolveSiteState;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SiteState;
use App\Features\Setup\Support\CachedPendingWork;
use App\Features\Setup\Support\CoreSeeds;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Models\User;
use App\Support\InstallMarker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/phpvms-site-state-'.uniqid();
    mkdir($this->dir);
    $this->state = new SetupRunState($this->dir.'/state.json');
    $this->completed = new UpgradeCompleted($this->dir.'/completed', databaseSignal: false);
    $this->marker = new InstallMarker($this->dir.'/installed');
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
    $this->defaultConnection = config('database.default');
    DB::table('users')->delete();
});

afterEach(function (): void {
    config(['database.default' => $this->defaultConnection]);
    DB::purge('broken');
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

function resolveSiteState(object $test): SiteState
{
    $detect = new DetectPendingWork($test->paths, app(CoreSeeds::class), $test->state);
    $cache = new CachedPendingWork($detect, $test->paths, app(CoreSeeds::class), $test->state, $test->dir.'/pending.json');

    return new ResolveSiteState($test->state, $test->completed, $test->marker, $cache)->handle();
}

function finishedRun(SetupRunState $state, bool $fresh, RunStatus $status): void
{
    $state->request('installer', $fresh);
    $state->markRunning();
    $state->finished($status);
}

function breakDatabase(): void
{
    config(['database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/phpvms.sqlite', 'prefix' => '']]);
    config(['database.default' => 'broken']);
    DB::purge('broken');
}

test('an unreachable database is Install without the marker and Unavailable with it', function (): void {
    breakDatabase();

    expect(resolveSiteState($this))->toBe(SiteState::Install);

    $this->marker->write();

    expect(resolveSiteState($this))->toBe(SiteState::Unavailable);
});

test('a site with no users and no completed run is Install', function (): void {
    expect(resolveSiteState($this))->toBe(SiteState::Install);

    finishedRun($this->state, true, RunStatus::Failed);

    expect(resolveSiteState($this))->toBe(SiteState::Install);
});

test('a site with no users and a completed run is FirstUser', function (): void {
    $this->completed->write();

    expect(resolveSiteState($this))->toBe(SiteState::FirstUser);

    finishedRun($this->state, true, RunStatus::Done);

    expect(resolveSiteState($this))->toBe(SiteState::FirstUser);
});

test('a completed marker with an unfinished run is still Install', function (): void {
    $this->completed->write();
    finishedRun($this->state, true, RunStatus::Failed);

    expect(resolveSiteState($this))->toBe(SiteState::Install);
});

test('a site whose only user is soft deleted is not fresh', function (): void {
    User::factory()->create()->delete();

    expect(resolveSiteState($this))->toBe(SiteState::V7Upgrade);
});

test('users without a completed marker are a V7Upgrade', function (): void {
    User::factory()->create();

    expect(resolveSiteState($this))->toBe(SiteState::V7Upgrade);
});

test('a demo install that seeded users and then failed is Install, not V7Upgrade', function (): void {
    User::factory()->create();
    finishedRun($this->state, true, RunStatus::Failed);

    expect(resolveSiteState($this))->toBe(SiteState::Install);
});

test('a fresh run that finished leaves users as current', function (): void {
    User::factory()->create();
    $this->completed->write();
    finishedRun($this->state, true, RunStatus::Done);

    expect(resolveSiteState($this))->toBe(SiteState::Current);
});

test('a completed site with pending work is UpdatePending', function (): void {
    User::factory()->create();
    $this->completed->write();
    $this->paths->pending = ['2026_01_01_000000_a'];

    expect(resolveSiteState($this))->toBe(SiteState::UpdatePending);
});

test('a completed site with an unfinished update run is UpdatePending', function (): void {
    User::factory()->create();
    $this->completed->write();
    finishedRun($this->state, false, RunStatus::Failed);

    expect(resolveSiteState($this))->toBe(SiteState::UpdatePending);
});

test('a completed site with nothing pending is Current', function (): void {
    User::factory()->create();
    $this->completed->write();

    expect(resolveSiteState($this))->toBe(SiteState::Current);
});

test('a failure that is not a lost connection is reported', function (): void {
    Exceptions::fake();
    User::factory()->create();
    $this->completed->write();
    $this->paths = new class() extends MigrationPaths
    {
        public function pendingSchema(): array
        {
            throw new LogicException('bug in detection');
        }
    };

    expect(resolveSiteState($this))->toBe(SiteState::Install);

    Exceptions::assertReported(fn (LogicException $e): bool => $e->getMessage() === 'bug in detection');
});
