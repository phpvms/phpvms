<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\Schemas\FlightBundleForm;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

it('uses the stock searchable Filament multi-select for subfleets', function (): void {
    $field = FlightBundleForm::subfleets();

    expect($field)
        ->toBeInstanceOf(Select::class)
        ->and($field->isMultiple())->toBeTrue()
        ->and($field->isSearchable())->toBeTrue()
        ->and($field->isPreloaded())->toBeTrue()
        ->and($field->getRelationshipName())->toBe('subfleets');
});

it('attaches subfleets through the edit-details slideover', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $bundle = FlightBundle::factory()->create();
    [$a, $b] = Subfleet::factory()->count(2)->create();

    Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->callAction('edit', ['subfleets' => [$a->id, $b->id]])
        ->assertHasNoActionErrors();

    expect($bundle->fresh()->subfleets->pluck('id')->sort()->values()->all())
        ->toBe([$a->id, $b->id]);
});

it('detaches a subfleet by saving a reduced selection', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $bundle = FlightBundle::factory()->create();
    [$a, $b] = Subfleet::factory()->count(2)->create();
    $bundle->subfleets()->attach([$a->id, $b->id]);

    // fillForm() can't shrink an array field: it dots the state into
    // per-index sets, and the vendor's numeric-key pruning misses the
    // mounted-action path — so write the whole array in one set().
    Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->mountAction('edit')
        ->set('mountedActions.0.data.subfleets', [$a->id])
        ->call('callMountedAction')
        ->assertHasNoActionErrors();

    expect($bundle->fresh()->subfleets->pluck('id')->all())->toBe([$a->id]);
});
