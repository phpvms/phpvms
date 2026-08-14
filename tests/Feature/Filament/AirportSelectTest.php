<?php

use App\Filament\Forms\Components\AirportSelect;
use App\Models\Aircraft;
use App\Models\Airport;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\Livewire;

/**
 * @property-read Schema $form
 */
class AirportSelectHarness extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(Aircraft::class)
            ->components([
                AirportSelect::make('airport_id')->airportRelationship('airport'),
            ])
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('shows local matches before missing vacentral matches', function (): void {
    Airport::factory()->create([
        'id'   => 'KFFC',
        'icao' => 'KFFC',
        'name' => 'Local Falcon Field',
    ]);

    Http::fake([
        'api.phpvms.net/v2/airports/search*' => Http::response([
            ['icao' => 'KFFC', 'name' => 'Remote Falcon Field'],
            ['icao' => 'KFFT', 'name' => 'Capital City Airport'],
        ]),
    ]);

    $results = AirportSelect::make('airport_id')
        ->airportRelationship('airport')
        ->getSearchResults('kff');

    expect($results)
        ->toHaveKey('KFFC', 'KFFC - Local Falcon Field')
        ->and(array_key_first($results))->toBe('KFFC')
        ->and($results['From Airport Lookup'])->toBe([
            'vacentral:KFFT' => 'KFFT - Capital City Airport',
        ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.phpvms.net/v2/airports/search?text=kff');
});

it('shows no local results before airport lookup results', function (): void {
    Http::fake([
        'api.phpvms.net/v2/airports/search*' => Http::response([
            ['icao' => 'KFFT', 'name' => 'Capital City Airport'],
        ]),
    ]);

    $select = AirportSelect::make('airport_id')
        ->airportRelationship('airport');

    expect($select->getSearchResults('kff'))->toBe([
        '__no_local_airports__' => 'No local airports found',
        'From Airport Lookup'   => [
            'vacentral:KFFT' => 'KFFT - Capital City Airport',
        ],
    ])->and($select->isOptionDisabled('__no_local_airports__', 'No local airports found'))->toBeTrue();
});

it('keeps local matches when vacentral search fails', function (): void {
    Airport::factory()->create([
        'id'   => 'KFFL',
        'icao' => 'KFFL',
        'name' => 'Municipal Airport',
    ]);

    Http::fake([
        'api.phpvms.net/v2/airports/search*' => Http::response(null, 503),
    ]);

    $results = AirportSelect::make('airport_id')
        ->airportRelationship('airport')
        ->getSearchResults('kff');

    expect($results)->toBe([
        'KFFL' => 'KFFL - Municipal Airport',
    ]);
});

it('adds a selected vacentral airport and replaces the temporary value', function (): void {
    Http::fake([
        'api.phpvms.net/v1/airports/ZZZQ' => Http::response([
            'icao'      => 'ZZZQ',
            'iata'      => 'ZZQ',
            'name'      => 'Quartz Field',
            'city'      => 'Quartz City',
            'country'   => 'US',
            'region'    => 'US-TX',
            'tz'        => 'America/Chicago',
            'elevation' => 725,
            'lat'       => 30.1,
            'lon'       => -97.7,
        ]),
    ]);

    Livewire::test(AirportSelectHarness::class)
        ->set('data.airport_id', 'vacentral:ZZZQ')
        ->assertSet('data.airport_id', 'ZZZQ');

    expect(Airport::find('ZZZQ'))
        ->name->toBe('Quartz Field')
        ->location->toBe('Quartz City');
});
