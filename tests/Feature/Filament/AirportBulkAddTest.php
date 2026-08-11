<?php

declare(strict_types=1);

use App\Filament\Resources\Airports\Pages\ListAirports;
use App\Models\Airport;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Http::preventStrayRequests();
});

it('replaces the create actions with one add-airports drawer', function (): void {
    $page = livewireInstance(ListAirports::class);
    $actions = collect($page->getCachedHeaderActions())
        ->whereInstanceOf(Action::class)
        ->keyBy(fn (Action $action): string => $action->getName());
    $action = $actions->get('addAirports');

    expect($actions)
        ->toHaveKey('addAirports')
        ->not->toHaveKeys(['create', 'bulkAdd'])
        ->and($action->getLabel())->toBe('Add Airports')
        ->and($action->isModalSlideOver())->toBeTrue()
        ->and($action->getModalSubmitActionLabel())->toBe('Save and exit');

    Livewire::test(ListAirports::class)
        ->mountAction('addAirports')
        ->assertSchemaComponentExists(
            'airportSearchSelection',
            null,
            fn (Select $component): bool => $component->isSearchable()
                && !$component->isNative()
                && $component->isAutofocused(),
        );
})->group('filament');

it('queues selected search results without saving until the drawer is submitted', function (): void {
    Http::fake(function (Request $request) {
        $icao = match ($request->data()['text'] ?? null) {
            'zzza'  => 'ZZZA',
            'zzzb'  => 'ZZZB',
            default => null,
        };

        if ($icao === null) {
            return Http::response([]);
        }

        return Http::response([airportLookupResult($icao)]);
    });

    $component = Livewire::test(ListAirports::class)
        ->mountAction('addAirports')
        ->call('searchAirports', 'zzza')
        ->assertSet('queuedAirports', [])
        ->call('queueSelectedAirport', 'ZZZA')
        ->call('searchAirports', 'zzzb')
        ->call('queueSelectedAirport', 'ZZZB');

    expect($component->get('queuedAirports'))
        ->toHaveKeys(['ZZZA', 'ZZZB'])
        ->and($component->get('queuedAirports.ZZZA.title'))->toBe('ZZZA - Alpha Field')
        ->and($component->get('queuedAirports.ZZZA.timezone'))->toBe('America/New_York')
        ->and($component->get('queuedAirports.ZZZA.display_location'))->toBe('Alphaville, NY, US')
        ->and(Airport::firstWhere('icao', 'ZZZA'))->toBeNull()
        ->and(Airport::firstWhere('icao', 'ZZZB'))->toBeNull();

    $component->callMountedAction()->assertNotified();

    expect(Airport::firstWhere('icao', 'ZZZA')?->name)->toBe('Alpha Field')
        ->and(Airport::firstWhere('icao', 'ZZZB')?->name)->toBe('Bravo Field');
})->group('filament');

it('warns when a searched airport already exists', function (): void {
    Airport::factory()->create([
        'id'   => 'ZZZA',
        'icao' => 'ZZZA',
    ]);

    Http::fake([
        'api.phpvms.net/v2/airports/search*' => Http::response([
            airportLookupResult('ZZZA'),
        ]),
    ]);

    Livewire::test(ListAirports::class)
        ->mountAction('addAirports')
        ->call('searchAirports', 'zzza')
        ->call('queueSelectedAirport', 'ZZZA')
        ->assertSet('queuedAirports', [])
        ->assertNotified('ZZZA already exists');
})->group('filament');

it('waits for a selection when a search returns multiple airports', function (): void {
    Http::fake([
        'api.phpvms.net/v2/airports/search*' => Http::response([
            airportLookupResult('ZZZA'),
            airportLookupResult('ZZZB'),
        ]),
    ]);

    $component = Livewire::test(ListAirports::class)
        ->mountAction('addAirports')
        ->call('searchAirports', 'zzz')
        ->assertSet('queuedAirports', [])
        ->call('queueSelectedAirport', 'ZZZB');

    expect($component->get('queuedAirports'))
        ->toHaveKey('ZZZB')
        ->not->toHaveKey('ZZZA')
        ->and(Airport::firstWhere('icao', 'ZZZB'))->toBeNull();
})->group('filament');

it('saves the manual airport form when the queue is empty', function (): void {
    Livewire::test(ListAirports::class)
        ->mountAction('addAirports')
        ->fillForm([
            'icao' => 'ZZZM',
            'name' => 'Manual Field',
            'lat'  => 33.1,
            'lon'  => -84.2,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Airport::firstWhere('icao', 'ZZZM')?->name)->toBe('Manual Field');
})->group('filament');

/**
 * @return array<string, mixed>
 */
function airportLookupResult(string $icao): array
{
    $isAlpha = $icao === 'ZZZA';

    return [
        'icao'    => $icao,
        'iata'    => $isAlpha ? 'ZZA' : 'ZZB',
        'name'    => $isAlpha ? 'Alpha Field' : 'Bravo Field',
        'city'    => $isAlpha ? 'Alphaville' : 'Bravotown',
        'country' => $isAlpha ? 'US' : 'GB',
        'region'  => $isAlpha ? 'US-NY' : 'GB-ENG',
        'tz'      => $isAlpha ? 'America/New_York' : 'Europe/London',
        'alt'     => $isAlpha ? 13 : 83,
        'lat'     => $isAlpha ? 40.6413 : 51.4706,
        'lon'     => $isAlpha ? -73.7781 : -0.4619,
    ];
}
