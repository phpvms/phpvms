<?php

declare(strict_types=1);

use App\Enums\FlightType;
use App\Filament\Resources\Subfleets\Pages\EditSubfleet;
use App\Models\Subfleet;
use Database\Seeders\ShieldSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('renders the operational capability section with new fields', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();
    $subfleet = Subfleet::factory()->create();

    Livewire::test(EditSubfleet::class, ['record' => $subfleet->id])
        ->assertSuccessful()
        ->assertSee(__('filament.subfleets.sections.operational_capability'))
        ->assertSee('cruise_speed')
        ->assertSee('max_range_nm')
        ->assertSee('route_types');
});

it('persists capability values through the cast', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();
    $subfleet = Subfleet::factory()->create();

    Livewire::test(EditSubfleet::class, ['record' => $subfleet->id])
        ->set('data.cruise_speed', 450)
        ->set('data.max_range_nm', 3200)
        ->set('data.route_types', [FlightType::SCHED_PAX->value, FlightType::CHARTER_PAX_ONLY->value])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $subfleet->refresh();

    expect($subfleet->cruise_speed)->toBe(450)
        ->and($subfleet->max_range_nm)->toBe(3200)
        ->and($subfleet->route_types)->toBeInstanceOf(Collection::class)
        ->and($subfleet->route_types)->toHaveCount(2)
        ->and($subfleet->route_types->contains(FlightType::SCHED_PAX))->toBeTrue()
        ->and($subfleet->route_types->contains(FlightType::CHARTER_PAX_ONLY))->toBeTrue();

    // JSON-encoded storage via native AsEnumCollection.
    $rawJson = DB::table('subfleets')->where('id', $subfleet->id)->value('route_types');
    expect(json_decode((string) $rawJson, true))
        ->toMatchArray([FlightType::SCHED_PAX->value, FlightType::CHARTER_PAX_ONLY->value]);
});

it('persists empty route_types selection as empty collection', function (): void {
    $this->seed(ShieldSeeder::class);

    $admin = createAdminUser();
    $subfleet = Subfleet::factory()->create([
        'route_types' => [FlightType::SCHED_PAX],
    ]);

    Livewire::test(EditSubfleet::class, ['record' => $subfleet->id])
        ->set('data.cruise_speed')
        ->set('data.max_range_nm')
        ->set('data.route_types', [])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $subfleet->refresh();

    // Native AsEnumCollection stores `[]` as JSON `[]`, not null. The
    // "unrestricted" sentinel is null only. Consumers should treat both
    // as "no restriction" if that semantic is desired.
    expect($subfleet->route_types)->toBeInstanceOf(Collection::class)
        ->and($subfleet->route_types)->toHaveCount(0);
});
