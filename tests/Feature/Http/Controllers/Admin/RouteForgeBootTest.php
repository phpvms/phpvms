<?php

declare(strict_types=1);

use App\Enums\FlightType;
use App\Models\Airline;
use App\Models\User;
use Database\Seeders\ShieldSeeder;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);
});

it('rejects unauthenticated boot requests', function (): void {
    $this->getJson('/admin/route-forge/api/boot')
        ->assertStatus(401);
});

it('rejects authenticated users without the create:flight permission', function (): void {
    $this->actingAs(User::factory()->create());

    $this->getJson('/admin/route-forge/api/boot')
        ->assertStatus(403);
});

it('returns the boot envelope shape for an authorized user', function (): void {
    $this->actingAs(createAdminUser());
    Airline::factory()->create(['name' => 'Alpha Air', 'active' => true]);

    $payload = $this->getJson('/admin/route-forge/api/boot')
        ->assertSuccessful()
        ->json('data');

    expect($payload)->toHaveKeys([
        'csrf_token',
        'locale',
        'user',
        'airlines',
        'routes',
        'config',
        'translations',
    ])->and($payload)->not->toHaveKey('bundles');
});

it('exposes every RouteForge endpoint URL on the routes payload', function (): void {
    $this->actingAs(createAdminUser());

    $routes = $this->getJson('/admin/route-forge/api/boot')
        ->assertSuccessful()
        ->json('data.routes');

    expect($routes)->toHaveKeys([
        'preview_airports',
        'subfleets',
        'airline_stats',
        'lint',
        'commit',
        'bundles',
        'bundle_edit_template',
    ]);
    foreach ($routes as $url) {
        expect($url)->toBeString()->not->toBe('');
    }
});

it('includes only active airlines in the boot airlines payload', function (): void {
    $this->actingAs(createAdminUser());
    $active = Airline::factory()->create(['name' => 'Active Co', 'active' => true]);
    $inactive = Airline::factory()->create(['name' => 'Inactive Co', 'active' => false]);

    $airlines = $this->getJson('/admin/route-forge/api/boot')
        ->assertSuccessful()
        ->json('data.airlines');

    $ids = array_map(static fn (array $row): int => (int) $row['id'], $airlines);

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);
});

it('reports can_commit=true on the user payload for an authorized user', function (): void {
    $admin = createAdminUser();
    $this->actingAs($admin);

    $user = $this->getJson('/admin/route-forge/api/boot')
        ->assertSuccessful()
        ->json('data.user');

    expect($user['id'])->toBe($admin->id)
        ->and($user['can_commit'])->toBeTrue();
});

it('includes a flight_types IATA→label map in the boot translations payload', function (): void {
    $this->actingAs(createAdminUser());

    $flightTypes = $this->getJson('/admin/route-forge/api/boot')
        ->assertSuccessful()
        ->json('data.translations.flight_types');

    $expectedCodes = array_map(static fn (FlightType $case): string => $case->value, FlightType::cases());

    expect($flightTypes)->toBeArray()
        ->and(array_keys($flightTypes))->toEqualCanonicalizing($expectedCodes)
        ->and($flightTypes)->toHaveCount(count($expectedCodes));

    foreach ($flightTypes as $value) {
        expect($value)->toBeString()->not->toBe('');
    }
});

it('resolves each flight_types entry to its FlightType::getLabel() value', function (): void {
    $this->actingAs(createAdminUser());

    $flightTypes = $this->getJson('/admin/route-forge/api/boot')
        ->assertSuccessful()
        ->json('data.translations.flight_types');

    foreach (FlightType::cases() as $case) {
        expect($flightTypes[$case->value])->toBe($case->getLabel());
    }
});
