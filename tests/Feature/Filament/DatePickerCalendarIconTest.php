<?php

declare(strict_types=1);

use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Models\FlightBundle;
use Database\Seeders\RolesPermissionsSeeder;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\DatePicker;
use Livewire\Livewire;

/**
 * BasePanelProvider::bootUsing() gives every date-bearing picker a calendar
 * suffix icon panel-wide. The registration lives in Filament's per-request
 * ScopedComponentManager, so it is only observable on components built
 * inside a booted-panel request — hence asserting against the drawer's
 * schema rather than a picker instantiated in the test itself.
 */
it('gives date pickers a calendar suffix icon panel-wide', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $bundle = FlightBundle::factory()->create();

    Livewire::test(EditFlightBundle::class, ['record' => $bundle->getRouteKey()])
        ->mountAction('edit')
        ->assertSchemaComponentExists(
            'start_date',
            null,
            fn (DatePicker $component): bool => $component->getSuffixIcon() === Phosphor::CalendarLight,
        );
});
