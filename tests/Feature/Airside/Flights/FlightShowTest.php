<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Contracts\Metar as MetarProvider;
use App\Features\Flights\Actions\LoadStationWeather;
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

it('emits the route map island with the route as JSON', function (): void {
    $departure = $this->fixture['departure'];
    $arrival = $this->fixture['arrival'];
    $departure->update(['lat' => 40.64, 'lon' => -73.78]);
    $arrival->update(['lat' => 51.47, 'lon' => -0.45]);

    $html = $this->get('/flights/'.$this->fixture['flight']->id)->assertOk()->getContent();

    preg_match('#data-airside-island="route-map".*?<script type="application/json">(.*?)</script>#s', $html, $match);
    $data = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

    expect($data['from'])->toBe(['icao' => $departure->icao, 'lat' => 40.64, 'lon' => -73.78])
        ->and($data['to'])->toBe(['icao' => $arrival->icao, 'lat' => 51.47, 'lon' => -0.45])
        ->and($data['cruiseLevel'])->toBe(360)
        ->and($data['height'])->toBe(360)
        ->and($data['map'])->toHaveKeys(['basemapLight', 'basemapDark', 'layers']);
});

it('leaves the arrival out of the island when it has no coordinates', function (): void {
    $this->fixture['departure']->update(['lat' => 40.64, 'lon' => -73.78]);
    $this->fixture['arrival']->update(['lat' => null, 'lon' => null]);

    $html = $this->get('/flights/'.$this->fixture['flight']->id)->getContent();
    preg_match('#data-airside-island="route-map".*?<script type="application/json">(.*?)</script>#s', $html, $match);

    expect(json_decode($match[1], true)['to'])->toBeNull();
});

it('needs a bid for Generate OFP when SimBrief only serves bids, and follows a bid made from the drawer', function (): void {
    updateSetting('simbrief.api_key', 'key');
    updateSetting('simbrief.only_bids', true);

    $component = Livewire::test(FlightActions::class, ['flightId' => $this->fixture['flight']->id]);

    expect($component->get('detail')['policy']->simbriefEnabled)->toBeTrue()
        ->and($component->get('detail')['policy']->simbriefRequiresBid)->toBeTrue()
        ->and($component->get('detail')['flight']->summary->bidId)->toBeNull();

    $bid = Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    $component->dispatch('assignment-drawer.closed', flightId: $this->fixture['flight']->id, changed: true);

    expect($component->get('detail')['flight']->summary->bidId)->toBe($bid->id)
        ->and($component->get('detail')['flight']->summary->primaryAction)->toBe('overview');
});

it('marks the flight unavailable when another pilot holds it', function (): void {
    updateSetting('bids.disable_flight_on_bid', true);
    Bid::query()->create(['user_id' => User::factory()->create()->id, 'flight_id' => $this->fixture['flight']->id]);
    $this->fixture['flight']->update(['has_bid' => true]);

    $summary = Livewire::test(FlightActions::class, ['flightId' => $this->fixture['flight']->id])->get('detail')['flight']->summary;

    expect($summary->primaryAction)->toBe('unavailable')
        ->and($summary->availabilityReason)->toBe('Another pilot has a bid on this flight');
});

it('redirects to the dashboard when the flight is not visible', function (): void {
    $this->get('/flights/does-not-exist')->assertRedirect(route('frontend.dashboard.index'));
});

describe('weather states', function (): void {
    it('reads the report and when it was observed', function (): void {
        airsideFakeMetar(sprintf('KJFK %sZ 18010KT 10SM FEW020 20/10 A3000', gmdate('dHi')));

        $weather = app(LoadStationWeather::class)->handle('KJFK');

        expect($weather['state'])->toBe('loaded')
            ->and($weather['metar'])->toContain('18010KT 10SM')
            ->and($weather['observedAt'])->not->toBeNull();
    });

    it('marks an old report as stale', function (): void {
        airsideFakeMetar(sprintf('KJFK %sZ 18010KT 10SM FEW020 20/10 A3000', gmdate('dHi', time() - 7200)));

        expect(app(LoadStationWeather::class)->handle('KJFK')['state'])->toBe('stale');
    });

    it('reports a missing report when the provider has none', function (): void {
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

        expect(app(LoadStationWeather::class)->handle('KJFK'))->toBe(['state' => 'missing', 'metar' => null, 'observedAt' => null]);
    });

    it('reports a provider error', function (): void {
        airsideFakeMetar(null, new RuntimeException('down'));

        expect(app(LoadStationWeather::class)->handle('KJFK')['state'])->toBe('error');
    });

    it('reports a missing station', function (): void {
        expect(app(LoadStationWeather::class)->handle(null)['state'])->toBe('missing-station');
    });
});
