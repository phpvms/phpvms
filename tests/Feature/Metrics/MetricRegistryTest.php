<?php

declare(strict_types=1);

use App\Support\Metrics\MetricRegistry;

it('registers a metric and exposes it through has() and get()', function (): void {
    $registry = new MetricRegistry();

    $registry->register([
        'id'      => 'test.metric',
        'label'   => 'Test Metric',
        'resolve' => fn (): int => 42,
    ]);

    expect($registry->has('test.metric'))->toBeTrue()
        ->and($registry->get('test.metric')['id'])->toBe('test.metric')
        ->and($registry->get('test.metric')['label'])->toBe('Test Metric');
});

it('rejects a definition without a string id', function (): void {
    $registry = new MetricRegistry();

    $registry->register([
        'resolve' => fn (): int => 1,
    ]);
})->throws(InvalidArgumentException::class);

it('rejects a definition without a resolve closure', function (): void {
    $registry = new MetricRegistry();

    $registry->register([
        'id' => 'test.metric',
    ]);
})->throws(InvalidArgumentException::class);

it('lets a later registration override an earlier one by id', function (): void {
    $registry = new MetricRegistry();

    $registry->register(['id' => 'test.metric', 'label' => 'First', 'resolve' => fn (): int => 1]);
    $registry->register(['id' => 'test.metric', 'label' => 'Second', 'resolve' => fn (): int => 2]);

    expect($registry->get('test.metric')['label'])->toBe('Second')
        ->and($registry->all())->toHaveCount(1);
});

it('enumerates the full catalog through all()', function (): void {
    $registry = new MetricRegistry();

    $registry->register(['id' => 'test.a', 'resolve' => fn (): int => 1]);
    $registry->register(['id' => 'test.b', 'resolve' => fn (): int => 2]);

    expect($registry->all())->toHaveCount(2)
        ->and(collect($registry->all())->pluck('id')->all())->toBe(['test.a', 'test.b']);
});

it('applies documented defaults for optional definition keys', function (): void {
    $registry = new MetricRegistry();

    $registry->register(['id' => 'test.metric', 'resolve' => fn (): int => 1]);

    $definition = $registry->get('test.metric');

    expect($definition['group'])->toBe('general')
        ->and($definition['value_type'])->toBe('integer')
        ->and($definition['format'])->toBe('integer')
        ->and($definition['ttl'])->toBe(300)
        ->and($definition['parameters'])->toBe([]);
});

it('leaves no trace of a metric whose owning provider never boots', function (): void {
    // Simulates a disabled addon: register() is simply never called for its
    // id because its ServiceProvider::boot() never runs. There is no runtime
    // "enabled" flag anywhere in the registry to check -- absence from the
    // catalog IS the disable-safety guarantee (design.md D1).
    $registry = new MetricRegistry();

    expect($registry->has('addon.disabled_metric'))->toBeFalse()
        ->and($registry->get('addon.disabled_metric'))->toBeNull();
});

it('removes a metric by id', function (): void {
    $registry = new MetricRegistry();

    $registry->register(['id' => 'test.metric', 'resolve' => fn (): int => 1]);
    $registry->remove('test.metric');

    expect($registry->has('test.metric'))->toBeFalse();
});
