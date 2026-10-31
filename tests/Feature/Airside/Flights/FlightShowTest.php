<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Contracts\Metar as MetarProvider;
use App\Features\Bids\Livewire\AssignmentDrawer;
use App\Features\Flights\Livewire\AirportWeather;
use App\Features\Flights\Livewire\FlightActions;
use App\Models\Bid;
use App\Models\User;
use Livewire\Livewire;

/**
 * 7.4: the flight page: the hero, the facts, the route map island and the weather cards.
 */
function airsideFakeMetar(?string $metar, ?Throwable $failure = null): void
{
    app()->instance(MetarProvider::class, new class($metar, $failure) extends MetarProvider
    {
        public function __construct(private readonly ?string $metar, private readonly ?Throwable $failure) {}

        public function metar(string $icao): string
        {
            if ($this->failure instanceof Throwable) {
                throw $this->failure;
            }

            return $this->metar ?? '';
        }

        public function taf(string $icao): string
        {
            return '';
        }

        protected function get_metar($icao): string
        {
            return '';
        }

        protected function get_taf($icao): string
        {
            return '';
        }
    });
}

beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
    $this->fixture = airsideDispatchFixture(flight: ['level' => 360, 'dpt_time' => '0930', 'arr_time' => '1215', 'route_code' => 'A']);
    $this->actingAs($this->fixture['user']);
});

it('renders the hero, the facts, the route and the static OFP card', function (): void {
    $flight = $this->fixture['flight'];

    $this->get('/flights/'.$flight->id)
        ->assertOk()
        ->assertSee($flight->ident)
        ->assertSee('0930')
        ->assertSee('1215')
        ->assertSee('FL360')
        ->assertSee('DCT TEST')
        ->assertSeeLivewire(FlightActions::class)
        ->assertSeeLivewire(AssignmentDrawer::class);
});

it('emits the route map island with the route as JSON', function (): void {
    $departure = $this->fixture['departure'];
    $arrival = $this->fixture['arrival'];
    $departure->update(['lat' => 40.64, 'lon' => -73.78]);
    $arrival->update(['lat' => 51.47, 'lon' => -0.45]);

    $html = $this->get('/flights/'.$this->fixture['flight']->id)->assertOk()->getContent();

    expect($html)->toContain('data-airside-island="route-map"');

    preg_match('#data-airside-island="route-map".*?<script type="application/json">(.*?)</script>#s', $html, $match);
    $data = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

    expect($data['from'])->toBe(['icao' => $departure->icao, 'lat' => 40.64, 'lon' => -73.78])
        ->and($data['to'])->toBe(['icao' => $arrival->icao, 'lat' => 51.47, 'lon' => -0.45])
        ->and($data['cruiseLevel'])->toBe(360)
        ->and($data['height'])->toBe(360)
        ->and($data['map'])->toHaveKeys(['basemapLight', 'basemapDark', 'layers']);
});

it('shows the map empty state when the departure has no coordinates', function (): void {
    $this->fixture['departure']->update(['lat' => null, 'lon' => null]);

    $this->get('/flights/'.$this->fixture['flight']->id)
        ->assertOk()
        ->assertSee('Route map unavailable because airport coordinates are missing.')
        ->assertDontSee('data-airside-island="route-map"', false);
});

it('leaves the arrival out of the island when it has no coordinates', function (): void {
    $this->fixture['departure']->update(['lat' => 40.64, 'lon' => -73.78]);
    $this->fixture['arrival']->update(['lat' => null, 'lon' => null]);

    $html = $this->get('/flights/'.$this->fixture['flight']->id)->getContent();
    preg_match('#data-airside-island="route-map".*?<script type="application/json">(.*?)</script>#s', $html, $match);

    expect(json_decode($match[1], true)['to'])->toBeNull();
});

it('offers Generate OFP when SimBrief is on', function (): void {
    updateSetting('simbrief.api_key', 'key');

    Livewire::test(FlightActions::class, ['flightId' => $this->fixture['flight']->id])
        ->assertSee('Bid on flight')
        ->assertSee('Generate OFP');
});

it('needs a bid for Generate OFP when SimBrief only serves bids, and follows a bid made from the drawer', function (): void {
    updateSetting('simbrief.api_key', 'key');
    updateSetting('simbrief.only_bids', true);

    $component = Livewire::test(FlightActions::class, ['flightId' => $this->fixture['flight']->id])
        ->assertSee('Bid on flight')
        ->assertDontSee('Generate OFP');

    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    $component->dispatch('assignment-drawer.closed', flightId: $this->fixture['flight']->id, changed: true)
        ->assertSee('Open bid overview')
        ->assertSee('Generate OFP');
});

it('shows Unavailable when another pilot holds the flight', function (): void {
    updateSetting('bids.disable_flight_on_bid', true);
    Bid::query()->create(['user_id' => User::factory()->create()->id, 'flight_id' => $this->fixture['flight']->id]);
    $this->fixture['flight']->update(['has_bid' => true]);

    Livewire::test(FlightActions::class, ['flightId' => $this->fixture['flight']->id])
        ->assertSee('Unavailable')
        ->assertDontSee('Bid on flight');
});

it('redirects to the dashboard when the flight is not visible', function (): void {
    $this->get('/flights/does-not-exist')->assertRedirect(route('frontend.dashboard.index'));
});

describe('weather cards', function (): void {
    function weatherCard(?string $icao, string $label = 'Departure weather', ?string $empty = null)
    {
        return Livewire::withoutLazyLoading()->test(AirportWeather::class, ['label' => $label, 'icao' => $icao, 'emptyLabel' => $empty]);
    }

    it('shows the report and when it was observed', function (): void {
        airsideFakeMetar(sprintf('KJFK %sZ 18010KT 10SM FEW020 20/10 A3000', gmdate('dHi')));

        weatherCard('KJFK')
            ->assertSee('Departure weather')
            ->assertSee('KJFK')
            ->assertSee('18010KT 10SM')
            ->assertSee('Observed')
            ->assertDontSee('Stale report');
    });

    it('marks an old report as stale', function (): void {
        airsideFakeMetar(sprintf('KJFK %sZ 18010KT 10SM FEW020 20/10 A3000', gmdate('dHi', time() - 7200)));

        weatherCard('KJFK')->assertSee('Stale report')->assertSee('18010KT');
    });

    it('says when there is no current report', function (): void {
        app()->instance(MetarProvider::class, new class() extends MetarProvider
        {
            public function metar(string $icao): string
            {
                return '';
            }

            public function taf(string $icao): string
            {
                return 'TAF KJFK '.gmdate('dHi').'Z 18010KT P6SM SKC';
            }

            protected function get_metar($icao): string
            {
                return '';
            }

            protected function get_taf($icao): string
            {
                return '';
            }
        });

        weatherCard('KJFK')->assertSee('No current report for KJFK.');
    });

    it('shows a provider error with Retry', function (): void {
        airsideFakeMetar(null, new RuntimeException('down'));

        $card = weatherCard('KJFK')->assertSee('Weather provider error for KJFK.')->assertSee('Retry');

        airsideFakeMetar(sprintf('KJFK %sZ 18010KT 10SM FEW020 20/10 A3000', gmdate('dHi')));

        $card->call('retry')->assertSee('18010KT')->assertDontSee('Weather provider error');
    });

    it('shows the empty label for a missing station', function (): void {
        weatherCard(null, 'Alternate weather', 'No alternate airport')
            ->assertSee('Alternate weather')
            ->assertSee('No alternate airport');
        weatherCard(null)->assertSee('Weather station unavailable');
    });
});
