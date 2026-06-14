<?php

declare(strict_types=1);

use App\Models\FlightBundle;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
});

it('rejects unauthenticated bundles requests', function (): void {
    $this->getJson('/admin/route-forge/api/bundles')
        ->assertStatus(401);
});

it('rejects authenticated users without the edit:flight permission', function (): void {
    $this->actingAs(User::factory()->create());

    $this->getJson('/admin/route-forge/api/bundles')
        ->assertStatus(403);
});

it('returns the seeded bundles ordered by name when no search is supplied', function (): void {
    $this->actingAs(createAdminUser());
    FlightBundle::factory()->create(['name' => 'ZTest_Charlie']);
    FlightBundle::factory()->create(['name' => 'ZTest_Alpha']);
    FlightBundle::factory()->create(['name' => 'ZTest_Bravo']);

    $payload = $this->getJson('/admin/route-forge/api/bundles')
        ->assertSuccessful()
        ->json();

    $ourNames = array_values(array_filter(
        array_map(static fn (array $row): string => $row['name'], $payload['data']),
        static fn (string $name): bool => str_starts_with($name, 'ZTest_'),
    ));
    expect($ourNames)->toBe(['ZTest_Alpha', 'ZTest_Bravo', 'ZTest_Charlie']);
    expect($payload['meta'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);
});

it('filters bundles by case-insensitive name substring via search', function (): void {
    $this->actingAs(createAdminUser());
    FlightBundle::factory()->create(['name' => 'Spring 2026']);
    FlightBundle::factory()->create(['name' => 'Winter 2026']);
    FlightBundle::factory()->create(['name' => 'Cargo Night Ops']);

    $data = $this->getJson('/admin/route-forge/api/bundles?search=2026')
        ->assertSuccessful()
        ->json('data');

    $names = array_map(static fn (array $row): string => $row['name'], $data);
    expect($names)->toEqualCanonicalizing(['Spring 2026', 'Winter 2026']);
});

it('excludes soft-deleted bundles from the response', function (): void {
    $this->actingAs(createAdminUser());
    $live = FlightBundle::factory()->create(['name' => 'TestLive']);
    $deleted = FlightBundle::factory()->create(['name' => 'TestDeleted']);
    $deleted->delete();

    $data = $this->getJson('/admin/route-forge/api/bundles?search=Test')
        ->assertSuccessful()
        ->json('data');

    $ids = array_map(static fn (array $row): int => (int) $row['id'], $data);
    expect($ids)->toContain($live->id)
        ->and($ids)->not->toContain($deleted->id);
});

it('serializes bundle dates as YYYY-MM-DD strings or null', function (): void {
    $this->actingAs(createAdminUser());
    FlightBundle::factory()->create([
        'name'       => 'Dated Bundle',
        'start_date' => '2026-01-15',
        'end_date'   => '2026-02-15',
    ]);
    FlightBundle::factory()->create([
        'name'       => 'Open-ended Bundle',
        'start_date' => null,
        'end_date'   => null,
    ]);

    $data = $this->getJson('/admin/route-forge/api/bundles')
        ->assertSuccessful()
        ->json('data');

    $dated = collect($data)->firstWhere('name', 'Dated Bundle');
    $open = collect($data)->firstWhere('name', 'Open-ended Bundle');

    expect($dated['start_date'])->toBe('2026-01-15')
        ->and($dated['end_date'])->toBe('2026-02-15')
        ->and($open['start_date'])->toBeNull()
        ->and($open['end_date'])->toBeNull();
});
