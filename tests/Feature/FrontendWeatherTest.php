<?php

declare(strict_types=1);

use App\Contracts\Metar as MetarProvider;
use App\Models\User;
use App\Support\Metar;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

function weatherProvider(?string $metar = null, ?string $taf = null, ?Throwable $exception = null): MetarProvider
{
    return new class($metar, $taf, $exception) extends MetarProvider
    {
        public function __construct(
            private readonly ?string $metar,
            private readonly ?string $taf,
            private readonly ?Throwable $exception,
        ) {}

        public function metar(string $icao): string
        {
            if ($this->exception instanceof Throwable) {
                throw $this->exception;
            }

            return $this->metar ?? '';
        }

        public function taf(string $icao): string
        {
            return $this->taf ?? '';
        }

        protected function get_metar($icao): string
        {
            return '';
        }

        protected function get_taf($icao): string
        {
            return '';
        }
    };
}

function useWeatherProvider(?string $metar = null, ?string $taf = null, ?Throwable $exception = null): void
{
    app()->instance(MetarProvider::class, weatherProvider($metar, $taf, $exception));
}

function metarAt(int $timestamp): string
{
    return sprintf('KJFK %sZ 18010KT 10SM FEW020 20/10 A3000', gmdate('dHi', $timestamp));
}

test('returns current METAR observation metadata', function (): void {
    $metar = metarAt(time());
    useWeatherProvider($metar);

    $this->get('/api/weather/kjfk')
        ->assertOk()
        ->assertJsonPath('icao', 'KJFK')
        ->assertJsonPath('metar', $metar)
        ->assertJsonPath('observedAt', (new Metar($metar))->observed_date)
        ->assertJsonPath('isStale', false);
});

test('marks a METAR observed more than ninety minutes ago as stale', function (): void {
    $metar = metarAt(time() - (91 * 60));
    useWeatherProvider($metar);

    $this->get('/api/weather/KJFK')
        ->assertOk()
        ->assertJsonPath('observedAt', (new Metar($metar))->observed_date)
        ->assertJsonPath('isStale', true);
});

test('returns a successful response when only the TAF is available', function (): void {
    useWeatherProvider(null, 'TAF KJFK '.gmdate('dHi').'Z 18010KT P6SM SKC');

    $this->get('/api/weather/KJFK')
        ->assertOk()
        ->assertJsonPath('metar', null)
        ->assertJsonPath('observedAt', null)
        ->assertJsonPath('isStale', false);
});

test('rejects invalid ICAO codes', function (): void {
    $this->get('/api/weather/ABCDE')
        ->assertStatus(400)
        ->assertJsonPath('error', true)
        ->assertJsonPath('message', 'Invalid ICAO code.')
        ->assertJsonPath('icao', 'ABCDE');
});

test('does not expose provider exception text', function (): void {
    useWeatherProvider(exception: new RuntimeException('provider token leaked'));

    $response = $this->get('/api/weather/KJFK');

    $response
        ->assertStatus(503)
        ->assertJsonPath('error', true)
        ->assertJsonPath('message', 'METAR unavailable.')
        ->assertJsonPath('icao', 'KJFK')
        ->assertDontSee('provider token leaked');
});

test('returns unavailable when the provider supplies no METAR or TAF', function (): void {
    useWeatherProvider();

    $this->get('/api/weather/KJFK')
        ->assertStatus(503)
        ->assertJsonPath('error', true)
        ->assertJsonPath('icao', 'KJFK');
});
