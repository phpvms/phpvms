<?php

declare(strict_types=1);

use App\Filament\Resources\Airlines\Pages\CreateAirline;
use App\Filament\Resources\Airports\Pages\ListAirports;
use App\Models\Airline;
use App\Models\Airport;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

/**
 * icao/iata used to be pinned to real-world code lengths (3/2 for airlines,
 * 4/3 for airports) via Filament's `->length()`. That rule is now a
 * `->maxLength(8)`, matching the widened DB columns everywhere (see the
 * 2026_08_14_000000_widen_airports_iata migration for the one column that
 * needed a schema change).
 */
it('accepts 8-character airline icao and iata and rejects 9', function (): void {
    Livewire::test(CreateAirline::class)
        ->fillForm([
            'icao' => 'ABCDEFGH',
            'iata' => 'ABCDEFGH',
            'name' => 'Eight Char Air',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Airline::firstWhere('name', 'Eight Char Air'))
        ->icao->toBe('ABCDEFGH')
        ->iata->toBe('ABCDEFGH');

    Livewire::test(CreateAirline::class)
        ->fillForm([
            'icao' => 'ABCDEFGHI',
            'name' => 'Nine Char Air',
        ])
        ->call('create')
        ->assertHasFormErrors(['icao']);

    Livewire::test(CreateAirline::class)
        ->fillForm([
            'icao' => 'ABC',
            'iata' => 'ABCDEFGHI',
            'name' => 'Nine Char Iata Air',
        ])
        ->call('create')
        ->assertHasFormErrors(['iata']);
})->group('filament');

it('accepts an 8-character airport icao and iata, including on the airports.iata column widened by the new migration', function (): void {
    Livewire::test(ListAirports::class)
        ->mountAction('addAirports')
        ->fillForm([
            'icao' => 'ABCDEFGH',
            'iata' => 'ABCDEFGH',
            'name' => 'Eight Char Field',
            'lat'  => 10.0,
            'lon'  => 10.0,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Airport::firstWhere('icao', 'ABCDEFGH'))
        ->not->toBeNull()
        ->iata->toBe('ABCDEFGH');
})->group('filament');

it('rejects a 9-character airport icao', function (): void {
    Livewire::test(ListAirports::class)
        ->mountAction('addAirports')
        ->fillForm([
            'icao' => 'ABCDEFGHI',
            'name' => 'Nine Char Field',
            'lat'  => 10.0,
            'lon'  => 10.0,
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['icao']);
})->group('filament');
