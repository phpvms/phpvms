<?php

declare(strict_types=1);

use App\Support\Metrics\MetricFailureLog;
use App\Support\Metrics\Metrics;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('serves a repeat resolution inside the TTL from cache, running the resolver once', function (): void {
    $metrics = new Metrics(new MetricFailureLog());
    $calls = 0;

    $metrics->registry()->register([
        'id'      => 'test.counter',
        'ttl'     => 300,
        'resolve' => function () use (&$calls): int {
            $calls++;

            return 7;
        },
    ]);

    expect($metrics->resolve('test.counter'))->toBe(7)
        ->and($metrics->resolve('test.counter'))->toBe(7)
        ->and($calls)->toBe(1);
});

it("caches distinct parameter sets under distinct keys, neither returning the other's value", function (): void {
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'         => 'test.scoped',
        'parameters' => [
            'since' => ['type' => 'string', 'values' => ['7d', '30d']],
        ],
        'resolve' => fn (array $params): string => 'value-for-'.$params['since'],
    ]);

    expect($metrics->resolve('test.scoped', ['since' => '7d']))->toBe('value-for-7d')
        ->and($metrics->resolve('test.scoped', ['since' => '30d']))->toBe('value-for-30d');
});

it('issues zero aggregate queries for six metrics resolved on a warm cache', function (): void {
    $metrics = new Metrics(new MetricFailureLog());

    foreach (range(1, 6) as $i) {
        $metrics->registry()->register([
            'id'      => "test.warm.{$i}",
            'resolve' => static fn (): int => DB::table('users')->count(),
        ]);
    }

    // Warm the cache for every metric first.
    foreach (range(1, 6) as $i) {
        $metrics->resolve("test.warm.{$i}");
    }

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query;
    });

    foreach (range(1, 6) as $i) {
        $metrics->resolve("test.warm.{$i}");
    }

    expect($queries)->toBeEmpty();
});

it('bumping the namespace version re-resolves every metric without enumerating ids', function (): void {
    $metrics = new Metrics(new MetricFailureLog());
    $calls = 0;

    $metrics->registry()->register([
        'id'      => 'test.counter',
        'resolve' => function () use (&$calls): int {
            $calls++;

            return $calls;
        },
    ]);

    expect($metrics->resolve('test.counter'))->toBe(1)
        ->and($metrics->resolve('test.counter'))->toBe(1); // still cached

    $metrics->bumpNamespace();

    expect($metrics->resolve('test.counter'))->toBe(2); // re-resolved after the bump
});

it('contains a throwing resolver and returns the documented null result', function (): void {
    Log::spy();
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'      => 'test.broken',
        'resolve' => fn (): int => throw new RuntimeException('boom'),
    ]);

    expect($metrics->resolve('test.broken'))->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});

it('returns null and logs once for an unknown metric id', function (): void {
    Log::spy();
    $metrics = new Metrics(new MetricFailureLog());

    expect($metrics->resolve('does.not.exist'))->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});

it('writes at most one log entry when the same failing metric is resolved five times in one request', function (): void {
    Log::spy();
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'      => 'test.broken',
        'resolve' => fn (): int => throw new RuntimeException('boom'),
    ]);

    foreach (range(1, 5) as $i) {
        expect($metrics->resolve('test.broken'))->toBeNull();
    }

    Log::shouldHaveReceived('warning')->once();
});

it('logs independently per metric id, not globally once per request', function (): void {
    Log::spy();
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register(['id' => 'test.broken.a', 'resolve' => fn (): int => throw new RuntimeException('a')]);
    $metrics->registry()->register(['id' => 'test.broken.b', 'resolve' => fn (): int => throw new RuntimeException('b')]);

    $metrics->resolve('test.broken.a');
    $metrics->resolve('test.broken.b');

    Log::shouldHaveReceived('warning')->twice();
});

it('logs a failing metric again on the next request when the registry is a long-lived singleton', function (): void {
    Log::spy();
    $metrics = app('metrics');

    $metrics->registry()->register([
        'id'      => 'test.octane',
        'resolve' => fn (): int => throw new RuntimeException('boom'),
    ]);

    $metrics->resolve('test.octane');
    $metrics->resolve('test.octane');

    app()->forgetScopedInstances();

    $metrics->resolve('test.octane');

    Log::shouldHaveReceived('warning')->twice();
});
