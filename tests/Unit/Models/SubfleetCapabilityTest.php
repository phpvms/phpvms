<?php

declare(strict_types=1);

use App\Enums\FlightType;
use App\Models\Subfleet;
use Illuminate\Support\Collection;

it('creates subfleet with capability values and round-trips route_types through cast', function (): void {
    $subfleet = Subfleet::factory()->create([
        'cruise_speed' => 450,
        'max_range_nm' => 3200,
        'route_types'  => collect([FlightType::SCHED_PAX, FlightType::CHARTER_PAX_ONLY]),
    ]);

    $subfleet->refresh();

    expect($subfleet->cruise_speed)->toBe(450)
        ->and($subfleet->max_range_nm)->toBe(3200)
        ->and($subfleet->route_types)->toBeInstanceOf(Collection::class)
        ->and($subfleet->route_types)->toHaveCount(2)
        ->and($subfleet->route_types->contains(FlightType::SCHED_PAX))->toBeTrue()
        ->and($subfleet->route_types->contains(FlightType::CHARTER_PAX_ONLY))->toBeTrue();

    $this->assertDatabaseHas('subfleets', [
        'id'           => $subfleet->id,
        'cruise_speed' => 450,
        'max_range_nm' => 3200,
        'route_types'  => 'C,J',
    ]);
});

it('treats null route_types as unrestricted', function (): void {
    $subfleet = Subfleet::factory()->create([
        'route_types' => null,
    ]);

    $subfleet->refresh();

    expect($subfleet->route_types)->toBeNull();
});

it('collapses empty selection to null', function (): void {
    $subfleet = Subfleet::factory()->create([
        'route_types' => collect([]),
    ]);

    $subfleet->refresh();

    expect($subfleet->route_types)->toBeNull();

    $this->assertDatabaseHas('subfleets', [
        'id'          => $subfleet->id,
        'route_types' => null,
    ]);
});

it('falls back to config defaults when columns are null', function (): void {
    $subfleet = Subfleet::factory()->create([
        'cruise_speed' => null,
        'max_range_nm' => null,
        'route_types'  => null,
    ]);

    expect($subfleet->cruise_speed)->toBeNull()
        ->and($subfleet->max_range_nm)->toBeNull()
        ->and($subfleet->route_types)->toBeNull()
        ->and(config('phpvms.routeforge.cruise_speed_kt'))->toBe(450)
        ->and(config('phpvms.routeforge.mesh_warn_count'))->toBe(50);
});
