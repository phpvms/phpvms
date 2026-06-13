<?php

declare(strict_types=1);

use App\Models\Addon;
use Illuminate\Support\Carbon;

it('returns addons as the table name', function (): void {
    expect(new Addon()->getTable())->toBe('addons');
});

it('casts enabled to boolean', function (): void {
    $addon = Addon::factory()->create(['enabled' => 1]);

    expect($addon->enabled)->toBeBool()->toBeTrue();
});

it('casts installed_at to Carbon instance', function (): void {
    $addon = Addon::factory()->create(['installed_at' => now()]);

    expect($addon->installed_at)->toBeInstanceOf(Carbon::class);
});

it('accepts null for registry_id', function (): void {
    $addon = Addon::factory()->create(['registry_id' => null]);

    expect($addon->registry_id)->toBeNull();
});

it('accepts null for version', function (): void {
    $addon = Addon::factory()->create(['version' => null]);

    expect($addon->version)->toBeNull();
});

it('accepts null for installed_at', function (): void {
    $addon = Addon::factory()->create(['installed_at' => null]);

    expect($addon->installed_at)->toBeNull();
});

it('is fillable for all non-pk columns', function (): void {
    $fillable = new Addon()->getFillable();

    expect($fillable)->toContain('registry_id')
        ->toContain('type')
        ->toContain('version')
        ->toContain('namespace')
        ->toContain('path')
        ->toContain('enabled')
        ->toContain('installed_at');
});
