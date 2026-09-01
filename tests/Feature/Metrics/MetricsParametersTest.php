<?php

declare(strict_types=1);

use App\Support\Metrics\MetricFailureLog;
use App\Support\Metrics\Metrics;

it('resolves a valid declared parameter, passing it through to the resolver', function (): void {
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'         => 'test.scoped',
        'parameters' => [
            'since' => ['type' => 'string', 'values' => ['7d', '30d', '90d']],
        ],
        'resolve' => fn (array $params): string => 'scoped:'.$params['since'],
    ]);

    expect($metrics->resolve('test.scoped', ['since' => '30d']))->toBe('scoped:30d');
});

it('rejects an undeclared parameter without invoking the resolver', function (): void {
    $invoked = false;
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'      => 'test.scoped',
        'resolve' => function () use (&$invoked): int {
            $invoked = true;

            return 1;
        },
    ]);

    expect($metrics->resolve('test.scoped', ['unexpected' => 'value']))->toBeNull()
        ->and($invoked)->toBeFalse();
});

it('rejects a declared parameter value outside its permitted set without invoking the resolver', function (): void {
    $invoked = false;
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'         => 'test.scoped',
        'parameters' => [
            'since' => ['type' => 'string', 'values' => ['7d', '30d', '90d']],
        ],
        'resolve' => function () use (&$invoked): int {
            $invoked = true;

            return 1;
        },
    ]);

    expect($metrics->resolve('test.scoped', ['since' => '1y']))->toBeNull()
        ->and($invoked)->toBeFalse();
});

it('rejects a declared numeric parameter outside its permitted range without invoking the resolver', function (): void {
    $invoked = false;
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'         => 'test.scoped',
        'parameters' => [
            'days' => ['type' => 'int', 'min' => 1, 'max' => 90],
        ],
        'resolve' => function () use (&$invoked): int {
            $invoked = true;

            return 1;
        },
    ]);

    expect($metrics->resolve('test.scoped', ['days' => 365]))->toBeNull()
        ->and($invoked)->toBeFalse();
});

it('rejects a declared parameter supplied with the wrong type without invoking the resolver', function (): void {
    $invoked = false;
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'         => 'test.scoped',
        'parameters' => [
            'days' => ['type' => 'int', 'min' => 1, 'max' => 90],
        ],
        'resolve' => function () use (&$invoked): int {
            $invoked = true;

            return 1;
        },
    ]);

    expect($metrics->resolve('test.scoped', ['days' => 'thirty']))->toBeNull()
        ->and($invoked)->toBeFalse();
});

it('resolves with no parameters when none are supplied and none are declared', function (): void {
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'      => 'test.unscoped',
        'resolve' => fn (array $params): int => count($params),
    ]);

    expect($metrics->resolve('test.unscoped'))->toBe(0);
});
