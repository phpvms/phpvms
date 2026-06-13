<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Models\Addon;

beforeEach(function (): void {
    // The addons migration seeds bundled module rows; clear them so each test
    // starts with a known-empty state.
    Addon::query()->delete();

    $this->registry = app(AddonRegistry::class);
});

it('enable() sets the addon enabled', function (): void {
    Addon::factory()->create(['name' => 'Awards', 'path' => '/m/Awards', 'enabled' => false]);

    $this->registry->enable('Awards');

    expect(Addon::query()->where('name', 'Awards')->first()->enabled)->toBeTrue();
});

it('disable() clears the addon enabled flag', function (): void {
    Addon::factory()->create(['name' => 'Awards', 'path' => '/m/Awards', 'enabled' => true]);

    $this->registry->disable('Awards');

    expect(Addon::query()->where('name', 'Awards')->first()->enabled)->toBeFalse();
});

it('delete() removes the DB row', function (): void {
    Addon::factory()->create(['name' => 'Awards', 'path' => '/m/Awards', 'enabled' => false]);

    $this->registry->delete('Awards');

    expect(Addon::query()->where('name', 'Awards')->exists())->toBeFalse();
});

it('enable()/disable()/delete() no-op on an unknown addon', function (): void {
    $this->registry->enable('Nope');
    $this->registry->disable('Nope');
    $this->registry->delete('Nope');

    expect(Addon::query()->count())->toBe(0);
});
