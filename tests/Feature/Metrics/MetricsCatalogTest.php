<?php

declare(strict_types=1);

use App\Support\Metrics\MetricFailureLog;
use App\Support\Metrics\Metrics;

it('drives a picker from the catalog rather than a hard-coded list', function (): void {
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'      => 'test.metric',
        'label'   => 'Test Metric',
        'group'   => 'testing',
        'resolve' => fn (): int => 1,
    ]);

    $catalog = $metrics->all();

    expect($catalog)->toHaveCount(1)
        ->and($catalog[0]['id'])->toBe('test.metric')
        ->and($catalog[0]['label'])->toBe('Test Metric')
        ->and($catalog[0]['group'])->toBe('testing');
});

it('lets a stored metric reference be validated against the catalog before render', function (): void {
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register(['id' => 'test.metric', 'resolve' => fn (): int => 1]);

    expect($metrics->has('test.metric'))->toBeTrue()
        ->and($metrics->has('test.metric.renamed'))->toBeFalse();
});

it('applies the same declared display format for every consumer of the same metric', function (): void {
    $metrics = new Metrics(new MetricFailureLog());

    $metrics->registry()->register([
        'id'      => 'test.hours',
        'format'  => 'decimal_1',
        'resolve' => fn (): float => 12.34,
    ]);

    $value = $metrics->resolve('test.hours');

    // Two independent consumers formatting the same resolved value must
    // render the same string -- format is metadata on the definition, not a
    // per-consumer decision (design.md D6).
    $first = $metrics->format('test.hours', $value);
    $second = $metrics->format('test.hours', $value);

    expect($first)->toBe($second)
        ->and($first)->toBe('12.3');
});

it('returns null formatting an unknown metric id', function (): void {
    $metrics = new Metrics(new MetricFailureLog());

    expect($metrics->format('does.not.exist', 42))->toBeNull();
});
