<?php

declare(strict_types=1);

use App\Features\Setup\Steps\PrepareCacheTables;
use App\Features\Setup\Support\StepContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const CACHE_MIGRATION = '2025_01_13_163743_create_cache_table';

function cacheStepContext(array &$lines): StepContext
{
    return new StepContext(
        false,
        static function (string $line) use (&$lines): void {
            $lines[] = $line;
        },
        static function (int $done, int $total, ?string $item): void {},
    );
}

function forgetCacheMigration(): void
{
    DB::table('migrations')->where('migration', CACHE_MIGRATION)->delete();
}

afterEach(function (): void {
    // Leave the schema in its migrated shape for the next test.
    if (!Schema::hasTable('cache') || !Schema::hasTable('cache_locks')) {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
        DB::table('migrations')->where('migration', CACHE_MIGRATION)->delete();
        Artisan::call('migrate', ['--force' => true]);
    }
});

test('drops an addon-made cache table, and migrate then creates cache and cache_locks', function (): void {
    Schema::dropIfExists('cache');
    Schema::dropIfExists('cache_locks');
    forgetCacheMigration();
    Schema::create('cache', function (Blueprint $table): void {
        $table->string('key', 191)->unique();
        $table->mediumText('value');
        $table->integer('expiration');
    });

    $lines = [];
    $step = new PrepareCacheTables();
    expect($step->applies(cacheStepContext($lines)))->toBeTrue();
    $step->run(cacheStepContext($lines));
    expect(Schema::hasTable('cache'))->toBeFalse();

    Artisan::call('migrate', ['--force' => true]);

    expect(Schema::hasTable('cache'))->toBeTrue()
        ->and(Schema::hasTable('cache_locks'))->toBeTrue()
        ->and(Schema::hasColumn('cache_locks', 'owner'))->toBeTrue()
        ->and($lines)->not->toBeEmpty();
});

test('creates cache_locks when the migration ran but the table is missing', function (): void {
    Schema::dropIfExists('cache_locks');

    $lines = [];
    $step = new PrepareCacheTables();
    expect($step->applies(cacheStepContext($lines)))->toBeTrue();
    $step->run(cacheStepContext($lines));

    expect(Schema::hasTable('cache_locks'))->toBeTrue()
        ->and(Schema::hasColumns('cache_locks', ['key', 'owner', 'expiration']))->toBeTrue()
        ->and(Schema::hasTable('cache'))->toBeTrue()
        ->and($lines)->toHaveCount(1);
});

test('is skipped when both tables exist and the migration is recorded', function (): void {
    $lines = [];

    expect(new PrepareCacheTables()->applies(cacheStepContext($lines)))->toBeFalse();
});
