<?php

declare(strict_types=1);

use App\Filament\Resources\Subfleets\Pages\EditSubfleet;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Pages\EditAircraft;
use App\Models\Aircraft;
use App\Models\SimBriefAirframe;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Livewire\Livewire;

/**
 * Both airframe pickers used to open on an empty "Start typing to search"
 * list. They now seed an opening list scoped to the subfleet's type.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();

    SimBriefAirframe::create(['icao' => 'B738', 'name' => 'PMDG 737-800', 'airframe_id' => 'a1']);
    SimBriefAirframe::create(['icao' => 'B738', 'name' => 'iniBuilds 737-800', 'airframe_id' => 'a2']);
    SimBriefAirframe::create(['icao' => 'A320', 'name' => 'FlyByWire A320neo', 'airframe_id' => 'a3']);
});

function selectOptions(Schema $schema, string $name): array
{
    /** @var Select $select */
    $select = $schema->getComponent(
        fn (Component $component): bool => $component instanceof Select && $component->getName() === $name,
    );

    return $select->getOptions();
}

it('opens the subfleet airframe list on the airframes for its type', function (): void {
    // The variant suffix is stripped, so "B.738-WL" still finds the B738 rows.
    $subfleet = Subfleet::factory()->create(['type' => 'B.738-WL']);

    $options = selectOptions(
        Livewire::test(EditSubfleet::class, ['record' => $subfleet->getRouteKey()])
            ->assertSuccessful()
            ->instance()
            ->getSchema('form'),
        'simbrief_type',
    );

    expect($options)->toHaveKeys(['a1', 'a2'])
        ->and($options)->not->toHaveKey('a3');
});

it('opens the aircraft ICAO list on the parent subfleet type', function (): void {
    $subfleet = Subfleet::factory()->create(['type' => 'B738']);
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id]);

    $page = Livewire::test(EditAircraft::class, [
        'record'       => $aircraft->getRouteKey(),
        'parentRecord' => $subfleet,
    ])->mountAction('edit', ['recordKey' => $aircraft->getRouteKey()]);

    $instance = $page->instance();
    $options = selectOptions($instance->getSchema($instance->getMountedActionSchemaName()), 'icao');

    expect($options)->toHaveKey('B738')
        ->and($options)->not->toHaveKey('A320');
});

it('falls back to a general list when the type matches no airframe', function (): void {
    $subfleet = Subfleet::factory()->create(['type' => 'ZZZZ']);

    $options = selectOptions(
        Livewire::test(EditSubfleet::class, ['record' => $subfleet->getRouteKey()])
            ->assertSuccessful()
            ->instance()
            ->getSchema('form'),
        'simbrief_type',
    );

    // An unfiltered slice beats the empty dropdown this replaced.
    expect($options)->toHaveKeys(['a1', 'a2', 'a3']);
});
