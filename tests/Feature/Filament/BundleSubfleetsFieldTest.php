<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\Schemas\FlightBundleForm;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

/**
 * Bundle-level subfleets are edited through the InlineMultiSelect inside the
 * Edit-details slideover (the relation manager it replaced lives only in git
 * history). The option closures run over every subfleet, so an unguarded
 * $subfleet->airline->name would break the whole slideover for one
 * airline-less row — subfleets.airline_id is nullable even though the
 * factory always fills it, which is why nothing else covers that case.
 */
it('builds the subfleet options with an airline-less subfleet present', function (): void {
    $orphan = Subfleet::factory()->create(['airline_id' => null, 'name' => 'Orphan Fleet']);
    $normal = Subfleet::factory()->create(['name' => 'Mainline Fleet']);

    $field = FlightBundleForm::subfleets();

    expect($field->getOptionGroups())
        ->toHaveKey($normal->id)
        ->not->toHaveKey($orphan->id)
        ->and($field->getOptionMetas())
        ->toHaveKey($orphan->id);
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
