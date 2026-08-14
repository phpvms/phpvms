<?php

declare(strict_types=1);

use App\Filament\Resources\Subfleets\Resources\Aircraft\Pages\EditAircraft;
use App\Models\Aircraft;
use App\Models\SimBriefAirframe;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Livewire\Livewire;

it('renders the icao airframe dropdown with the stored value resolved from the airframes table', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    createAdminUser();
    $aircraft = Aircraft::factory()->create(['icao' => 'B738']);

    SimBriefAirframe::create([
        'icao'        => 'B738',
        'name'        => 'iniBuilds (MSFS) - 737-800',
        'airframe_id' => '3_1677576294832',
    ]);

    // The icao select is an identity field, so it lives in the overview's
    // drawer rather than the page form. The drawer's modal body is rendered
    // client-side, so read the resolved label off the mounted schema.
    $page = Livewire::test(EditAircraft::class, [
        'record'       => $aircraft->getRouteKey(),
        'parentRecord' => $aircraft->subfleet,
    ])
        ->mountAction('edit', ['recordKey' => $aircraft->getRouteKey()])
        ->assertHasNoActionErrors();

    $drawer = $page->instance();

    /** @var Select $icao */
    $icao = $drawer->getSchema($drawer->getMountedActionSchemaName())
        ->getComponent(fn (Component $component): bool => $component instanceof Select && $component->getName() === 'icao');

    expect($icao->getOptionLabel())->toBe('B738 - iniBuilds (MSFS) - 737-800');
});
