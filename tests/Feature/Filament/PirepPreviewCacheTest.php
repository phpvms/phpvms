<?php

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Pages\ListPireps;
use App\Http\Middleware\UpdatePending;
use App\Models\Pirep;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\Cache;

use function Pest\Livewire\livewire;

test('preview rows are cached while pending and refresh when the state changes', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->withoutMiddleware(UpdatePending::class);
    $this->actingAs(createAdminUser());

    $pirep = Pirep::factory()->create([
        'state'        => PirepState::PENDING,
        'submitted_at' => now(),
    ]);

    $data = livewire(ListPireps::class)->instance()->getPreviewData();
    $key = sprintf(
        'pirep:preview:%s:%s:%s',
        $pirep->id,
        $pirep->fresh()->updated_at->getTimestamp(),
        app()->getLocale(),
    );

    expect($data[$pirep->id]['state'])->toBe(PirepState::PENDING->getLabel())
        ->and(Cache::has($key))->toBeTrue();

    // A state change touches updated_at, so the cache key rolls over and the
    // payload regenerates — the old entry is simply never read again.
    $this->travel(2)->seconds();
    $pirep->update(['state' => PirepState::ACCEPTED]);

    $fresh = livewire(ListPireps::class)->instance()->getPreviewData();

    expect($fresh[$pirep->id]['state'])->toBe(PirepState::ACCEPTED->getLabel())
        ->and($fresh[$pirep->id]['chip'])->toBe('chip--ok');
});
