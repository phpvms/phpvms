<?php

use App\Enums\PirepState;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\RequiresActionWidget;
use App\Filament\Widgets\StatsStripWidget;
use App\Http\Middleware\UpdatePending;
use App\Models\Pirep;
use Database\Seeders\RolesPermissionsSeeder;

use function Pest\Livewire\livewire;

test('admin dashboard renders the welcome heading and the new widgets', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);

    $admin = createAdminUser(['name' => 'Jordan Rivera']);
    $pendingPirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($admin);

    // Page shell: heading + the three widgets mounted in the schema. The
    // widgets themselves are lazy-loaded (Filament's CanBeLazy default), so
    // their content isn't in this response — checked via livewire() below.
    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('Welcome back, Jordan Rivera')
        ->assertSee(StatsStripWidget::class, false)
        ->assertSee(RequiresActionWidget::class, false);

    livewire(StatsStripWidget::class)
        ->assertSee(__('filament.dashboard.reports_filed'))
        ->assertSee(__('filament.dashboard.distance'));

    livewire(RequiresActionWidget::class)
        ->assertSee($pendingPirep->ident);
});
