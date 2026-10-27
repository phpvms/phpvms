<?php

declare(strict_types=1);

use App\Filament\Resources\Airports\Pages\ListAirports;
use App\Jobs\PopulateMissingAirports;
use App\Models\Airport;
use App\Models\Flight;
use App\Services\AirportService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->admin = createAdminUser();
    $this->actingAs($this->admin);

    Http::preventStrayRequests();
});

it('lists airport ids that rows reference but no airport row carries', function (): void {
    $present = Airport::factory()->create();
    $trashed = Airport::factory()->create();
    $trashed->delete();

    Flight::factory()->create(['dpt_airport_id' => 'ZZZA', 'arr_airport_id' => 'ZZZB', 'alt_airport_id' => null]);
    Flight::factory()->create(['dpt_airport_id' => 'ZZZA', 'arr_airport_id' => $present->id, 'alt_airport_id' => $trashed->id]);

    expect(app(AirportService::class)->findMissingAirportIds())->toBe(['ZZZA' => 2, 'ZZZB' => 1]);
})->group('filament');

it('creates a missing airport under the id it is referenced by, not the catalog id', function (): void {
    Http::fake([
        '*/v1/airports/ZZZA' => Http::response(missingAirportLookup('ZZZA', catalogId: '0CEKlfDMt2gw')),
        '*/v1/airports/ZZZB' => Http::response([], 404),
    ]);

    new PopulateMissingAirports(['ZZZA', 'ZZZB'], $this->admin->id)->handle(app(AirportService::class));

    expect(Airport::find('ZZZA')?->name)->toBe('Alpha Field')
        ->and(Airport::find('0CEKlfDMt2gw'))->toBeNull()
        ->and(Airport::find('ZZZB'))->toBeNull()
        ->and($this->admin->notifications()->count())->toBe(1);
})->group('filament');

it('queues the lookup for the airports the drawer lists', function (): void {
    Queue::fake();
    Flight::factory()->create(['dpt_airport_id' => 'ZZZA', 'arr_airport_id' => 'ZZZB', 'alt_airport_id' => null]);

    Livewire::test(ListAirports::class)
        ->mountAction('findMissingAirports')
        ->assertSet('missingAirports', ['ZZZA' => 1, 'ZZZB' => 1])
        ->callMountedAction()
        ->assertNotified();

    Queue::assertPushed(
        PopulateMissingAirports::class,
        fn (PopulateMissingAirports $job): bool => $job->airportIds === ['ZZZA', 'ZZZB'] && $job->notifyUserId === $this->admin->id,
    );
})->group('filament');

it('offers no populate button when nothing is missing', function (): void {
    $component = Livewire::test(ListAirports::class)
        ->mountAction('findMissingAirports')
        ->assertSet('missingAirports', []);

    expect($component->instance()->getMountedAction()?->getModalSubmitAction())->toBeNull();
})->group('filament');

/**
 * @return array<string, mixed>
 */
function missingAirportLookup(string $icao, ?string $catalogId = null): array
{
    return [
        'icao'      => $icao,
        'iata'      => 'ZZA',
        'name'      => 'Alpha Field',
        'city'      => 'Alphaville',
        'country'   => 'US',
        'region'    => 'US-NY',
        'tz'        => 'America/New_York',
        'elevation' => 13,
        'lat'       => 40.6413,
        'lon'       => -73.7781,
        'id'        => $catalogId,
    ];
}
