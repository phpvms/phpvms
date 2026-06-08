<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Exceptions\AddonNotFoundException;
use App\Models\Addon;

beforeEach(function (): void {
    // The addons migration seeds bundled module rows; clear them so each test
    // starts with a known-empty state.
    Addon::query()->delete();

    $this->registry = app(AddonRegistry::class);
});

it('find() returns an addon by name', function (): void {
    Addon::factory()->create(['name' => 'VMSAcars', 'path' => '/m/VMSAcars']);

    expect($this->registry->find('VMSAcars'))->not->toBeNull()
        ->and($this->registry->find('VMSAcars')->getName())->toBe('VMSAcars');
});

it('find() returns null when no addon matches', function (): void {
    expect($this->registry->find('Nope'))->toBeNull();
});

it('find() matches a null-name row by its path basename', function (): void {
    Addon::factory()->create(['name' => null, 'path' => '/m/Awards']);

    expect($this->registry->find('Awards'))->not->toBeNull();
});

it('findOrFail() throws when no addon matches', function (): void {
    $this->registry->findOrFail('Nope');
})->throws(AddonNotFoundException::class);

it('enabled() returns only enabled addons', function (): void {
    Addon::factory()->create(['enabled' => true]);
    Addon::factory()->create(['enabled' => false]);

    expect($this->registry->enabled())->toHaveCount(1)
        ->and($this->registry->enabled()->first()->isEnabled())->toBeTrue();
});
