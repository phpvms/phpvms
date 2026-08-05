<?php

declare(strict_types=1);

use App\Filament\Pages\Maintenance;
use App\Jobs\BackfillPirepArchives;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
});

test('archive pireps action dispatches the backfill job and notifies', function (): void {
    $this->actingAs(createAdminUser());
    Bus::fake();

    Livewire::test(Maintenance::class)
        ->callAction('archivePireps')
        ->assertNotified();

    Bus::assertDispatched(BackfillPirepArchives::class);
});
