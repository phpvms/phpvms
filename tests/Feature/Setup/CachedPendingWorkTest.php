<?php

declare(strict_types=1);

use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Support\CachedPendingWork;
use App\Features\Setup\Support\CoreSeeds;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupRunState;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/phpvms-pending-cache-'.uniqid();
    mkdir($this->dir.'/migrations', recursive: true);
    $this->state = new SetupRunState($this->dir.'/state.json');
    app()->instance(SetupRunState::class, $this->state);

    $this->paths = new class($this->dir.'/migrations') extends MigrationPaths
    {
        /** @var list<string> */
        public array $pending = [];

        public function __construct(private readonly string $dir) {}

        public function schema(): array
        {
            return [$this->dir];
        }

        public function pendingSchema(): array
        {
            return $this->pending;
        }

        public function pendingData(): array
        {
            return [];
        }
    };
    $this->cache = new CachedPendingWork(new DetectPendingWork($this->paths, app(CoreSeeds::class), $this->state), $this->paths, app(CoreSeeds::class), $this->state, $this->dir.'/pending.json');
});

afterEach(function (): void {
    array_map(unlink(...), array_merge(glob($this->dir.'/migrations/*') ?: [], glob($this->dir.'/*.*') ?: []));
    @rmdir($this->dir.'/migrations');
    @rmdir($this->dir);
});

test('returns the cached verdict until a migration file list changes', function (): void {
    $this->paths->pending = ['2026_01_01_000000_a'];
    expect($this->cache->handle()->pendingMigrations)->toBe(['2026_01_01_000000_a']);

    $this->paths->pending = [];
    expect($this->cache->handle()->pendingMigrations)->toBe(['2026_01_01_000000_a']);

    touch($this->dir.'/migrations/2026_02_02_000000_b.php');
    expect($this->cache->handle()->pendingMigrations)->toBe([]);
});

test('keeps the full verdict through the cache', function (): void {
    $this->paths->pending = ['2026_01_01_000000_a'];
    $first = $this->cache->handle();
    $second = $this->cache->handle();

    expect($second)->toEqual($first)
        ->and($second->blocksPanel())->toBeTrue();
});

test('forget drops the cached verdict', function (): void {
    $this->paths->pending = ['2026_01_01_000000_a'];
    $this->cache->handle();
    $this->paths->pending = [];

    $this->cache->forget();

    expect($this->cache->handle()->pendingMigrations)->toBe([]);
});

test('a corrupt cache file is recomputed', function (): void {
    file_put_contents($this->dir.'/pending.json', '{nope');
    $this->paths->pending = ['2026_01_01_000000_a'];

    expect($this->cache->handle()->pendingMigrations)->toBe(['2026_01_01_000000_a']);
});

test('a verdict older than the TTL is recomputed', function (): void {
    $this->paths->pending = ['2026_01_01_000000_a'];
    $this->cache->handle();
    $this->paths->pending = [];

    $this->travel(30)->seconds();
    expect($this->cache->handle()->pendingMigrations)->toBe(['2026_01_01_000000_a']);

    $this->travel(31)->seconds();
    expect($this->cache->handle()->pendingMigrations)->toBe([]);
});

test('a change in the run state misses the cache', function (): void {
    $this->paths->pending = ['2026_01_01_000000_a'];
    $this->cache->handle();
    $this->paths->pending = [];

    expect($this->cache->handle()->pendingMigrations)->toBe(['2026_01_01_000000_a']);

    $this->state->request('installer', false);

    expect($this->cache->handle()->pendingMigrations)->toBe([]);

    $this->paths->pending = ['2026_01_01_000000_a'];
    $this->state->finished(RunStatus::Done);

    expect($this->cache->handle()->pendingMigrations)->toBe(['2026_01_01_000000_a']);
});
