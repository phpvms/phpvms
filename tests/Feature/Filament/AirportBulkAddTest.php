<?php

declare(strict_types=1);

use App\Filament\Resources\Airports\Pages\ListAirports;
use App\Models\Airport;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

// Use codes that are absent from the sample-data airports so the tests stay
// hermetic regardless of what is already seeded in the shared testing DB.
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Http::fake([
        'api.phpvms.net/v1/airports/ZZZA' => Http::response([
            'icao'      => 'ZZZA', 'iata' => 'ZZA', 'name' => 'Alpha Field',
            'city'      => 'Alphaville', 'country' => 'US', 'region' => 'NY', 'tz' => 'America/New_York',
            'elevation' => 13, 'lat' => 40.6413, 'lon' => -73.7781,
        ]),
        'api.phpvms.net/v1/airports/ZZZB' => Http::response([
            'icao'      => 'ZZZB', 'iata' => 'ZZB', 'name' => 'Bravo Field',
            'city'      => 'Bravotown', 'country' => 'GB', 'region' => 'ENG', 'tz' => 'Europe/London',
            'elevation' => 83, 'lat' => 51.4706, 'lon' => -0.4619,
        ]),
        // Unknown code -> non-successful response -> empty lookup -> error row.
        'api.phpvms.net/v1/airports/ZZZX' => Http::response(null, 404),
    ]);
});

it('queues pasted codes as pending rows, then creates airports one at a time', function (): void {
    $component = Livewire::test(ListAirports::class)
        ->set('bulkIcaoInput', 'zzza, zzzb')
        ->call('addBulkAirports');

    // Both queued as pending, input cleared, nothing persisted yet.
    expect(collect($component->get('bulkRows'))->pluck('status')->all())->toBe(['pending', 'pending']);
    $component->assertSet('bulkIcaoInput', '');
    expect(Airport::firstWhere('icao', 'ZZZA'))->toBeNull()
        ->and(Airport::firstWhere('icao', 'ZZZB'))->toBeNull();

    // Drive the lookups the way the browser loop does.
    $component->call('processNextBulkAirport')->assertReturned(1);
    $component->call('processNextBulkAirport')->assertReturned(0);

    expect(Airport::firstWhere('icao', 'ZZZA')?->name)->toBe('Alpha Field')
        ->and(Airport::firstWhere('icao', 'ZZZB')?->name)->toBe('Bravo Field');

    expect(collect($component->get('bulkRows'))->pluck('status')->all())->toBe(['added', 'added']);
})->group('filament');

it('flags an existing airport as updated and overwrites its lookup fields', function (): void {
    Airport::factory()->create(['icao' => 'ZZZA', 'name' => 'Stale Name', 'hub' => false]);

    $component = Livewire::test(ListAirports::class)
        ->set('bulkIcaoInput', 'ZZZA')
        ->call('addBulkAirports')
        ->call('processNextBulkAirport');

    expect($component->get('bulkRows')[0]['status'])->toBe('updated')
        ->and(Airport::firstWhere('icao', 'ZZZA')->name)->toBe('Alpha Field');
})->group('filament');

it('marks a failed lookup as an error and saves nothing', function (): void {
    $component = Livewire::test(ListAirports::class)
        ->set('bulkIcaoInput', 'ZZZX')
        ->call('addBulkAirports')
        ->call('processNextBulkAirport')
        ->assertReturned(0);

    expect($component->get('bulkRows')[0]['status'])->toBe('error')
        ->and(Airport::firstWhere('icao', 'ZZZX'))->toBeNull();
})->group('filament');

it('persists the hub flag when a row is toggled', function (): void {
    $component = Livewire::test(ListAirports::class)
        ->set('bulkIcaoInput', 'ZZZA')
        ->call('addBulkAirports')
        ->call('processNextBulkAirport');

    expect(Airport::firstWhere('icao', 'ZZZA')->hub)->toBeFalse();

    $component->call('toggleBulkHub', 0);

    expect(Airport::firstWhere('icao', 'ZZZA')->fresh()->hub)->toBeTrue()
        ->and($component->get('bulkRows')[0]['hub'])->toBeTrue();
})->group('filament');

it('opens the bulk-add modal and resets any prior rows', function (): void {
    Livewire::test(ListAirports::class)
        ->set('bulkRows', [['icao' => 'ZZZA', 'name' => 'x', 'hub' => false, 'status' => 'added']])
        ->mountAction('bulkAdd')
        ->assertActionMounted('bulkAdd')
        ->assertSet('bulkRows', []);
})->group('filament');
