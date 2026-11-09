<?php

declare(strict_types=1);

use App\Support\Metrics\Metric;

$resolve = static fn (): int => 1;

it('builds the same definitions the first-party provider registers', function (): void {
    $resolve = static fn (): int => 1;

    $cases = [
        [Metric::integer('pilots.total', $resolve, 'Total Pilots', 'pilots'), ['id' => 'pilots.total', 'label' => 'Total Pilots', 'group' => 'pilots', 'value_type' => 'integer', 'format' => 'integer', 'ttl' => 300]],
        [Metric::integer('flights.total', $resolve, 'Flights', 'flights'), ['id' => 'flights.total', 'label' => 'Flights', 'group' => 'flights', 'value_type' => 'integer', 'format' => 'integer', 'ttl' => 300]],
        [Metric::integer('pireps.total', $resolve, 'Flights Completed', 'flights'), ['id' => 'pireps.total', 'label' => 'Flights Completed', 'group' => 'flights', 'value_type' => 'integer', 'format' => 'integer', 'ttl' => 300]],
        [Metric::float('hours.total', $resolve, 'Hours Flown', 'flights', 'decimal_1'), ['id' => 'hours.total', 'label' => 'Hours Flown', 'group' => 'flights', 'value_type' => 'float', 'format' => 'decimal_1', 'ttl' => 300]],
        [Metric::integer('distance.total', $resolve, 'Distance Flown (nmi)', 'flights'), ['id' => 'distance.total', 'label' => 'Distance Flown (nmi)', 'group' => 'flights', 'value_type' => 'integer', 'format' => 'integer', 'ttl' => 300]],
        [Metric::integer('aircraft.total', $resolve, 'Total Aircraft', 'fleet'), ['id' => 'aircraft.total', 'label' => 'Total Aircraft', 'group' => 'fleet', 'value_type' => 'integer', 'format' => 'integer', 'ttl' => 300]],
        [Metric::integer('aircraft.active', $resolve, 'Active Aircraft', 'fleet'), ['id' => 'aircraft.active', 'label' => 'Active Aircraft', 'group' => 'fleet', 'value_type' => 'integer', 'format' => 'integer', 'ttl' => 300]],
    ];

    foreach ($cases as [$metric, $literal]) {
        $definition = $metric->toDefinition();

        expect($definition['resolve'])->toBe($resolve)
            ->and($definition['parameters'])->toBe([]);

        unset($definition['resolve'], $definition['parameters']);

        expect($definition)->toEqual($literal);
    }
});

it('applies the registry defaults to a minimal metric', function () use ($resolve): void {
    $definition = Metric::integer('x.y', $resolve)->toDefinition();

    expect($definition)->toMatchArray([
        'id'         => 'x.y', 'label' => 'x.y', 'group' => 'general',
        'value_type' => 'integer', 'format' => 'integer', 'ttl' => 300, 'parameters' => [],
    ]);
});

it('rejects an enum parameter without values', function () use ($resolve): void {
    Metric::integer('x', $resolve)->parameter('p', 'enum');
})->throws(InvalidArgumentException::class);

it('rejects min and max on a non-int parameter', function () use ($resolve): void {
    Metric::integer('x', $resolve)->parameter('p', 'string', min: 1);
})->throws(InvalidArgumentException::class);

it('returns a new instance from parameter() and leaves the original untouched', function () use ($resolve): void {
    $base = Metric::integer('x', $resolve);
    $with = $base->parameter('days', 'int', min: 1, max: 30)->parameter('kind', 'enum', ['a', 'b']);

    expect($with)->not->toBe($base)
        ->and($base->toDefinition()['parameters'])->toBe([])
        ->and($with->toDefinition()['parameters'])->toBe([
            'days' => ['type' => 'int', 'min' => 1, 'max' => 30],
            'kind' => ['type' => 'string', 'values' => ['a', 'b']],
        ]);
});
