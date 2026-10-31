<?php

declare(strict_types=1);

use App\Contracts\Metar as MetarProvider;
use App\Services\Metar\AviationWeather;
use App\Support\HttpClient;
use Illuminate\Support\Facades\Cache;

/*
 * Only a report was cached. An airport without a METAR or TAF (many small
 * fields have no station) was asked again on every page that showed its
 * weather, and the page waited for the answer each time. An empty answer is
 * now cached too, for less time than a report. A request that fails is still
 * not cached, so the weather comes back as soon as the provider does.
 */

beforeEach(function (): void {
    Cache::flush();
});

/** A provider answering $answers in turn ('' for no report, a Throwable to fail), counting the calls. */
function countingMetarProvider(array $answers): MetarProvider
{
    return new class($answers) extends MetarProvider
    {
        public int $calls = 0;

        public function __construct(private array $answers) {}

        protected function get_metar($icao): string
        {
            return $this->answer();
        }

        protected function get_taf($icao): string
        {
            return $this->answer();
        }

        private function answer(): string
        {
            $answer = $this->answers[min($this->calls++, count($this->answers) - 1)];
            if ($answer instanceof Throwable) {
                throw $answer;
            }

            return $answer;
        }
    };
}

it('asks once for a METAR that is there', function (): void {
    $provider = countingMetarProvider(['LSGG 311050Z 24008KT CAVOK 12/05 Q1021']);

    expect($provider->metar('LSGG'))->toStartWith('LSGG')
        ->and($provider->metar('LSGG'))->toStartWith('LSGG')
        ->and($provider->calls)->toBe(1);
});

it('remembers that a station has no METAR', function (): void {
    $provider = countingMetarProvider(['']);

    expect($provider->metar('LSGP'))->toBe('')
        ->and($provider->metar('LSGP'))->toBe('')
        ->and($provider->calls)->toBe(1);
});

it('remembers that a station has no TAF', function (): void {
    $provider = countingMetarProvider(['']);

    $provider->taf('LSGP');
    $provider->taf('LSGP');

    expect($provider->calls)->toBe(1);
});

it('asks again for a missing report sooner than for a report', function (): void {
    $provider = countingMetarProvider(['', 'LSGP 311050Z 24008KT CAVOK 12/05 Q1021']);
    $provider->metar('LSGP');

    $this->travel(config('cache.keys.METAR_WEATHER_LOOKUP.missing_time') + 1)->seconds();

    expect($provider->metar('LSGP'))->toStartWith('LSGP')
        ->and($provider->calls)->toBe(2);
});

it('does not remember a request that failed', function (): void {
    $provider = countingMetarProvider([new RuntimeException('timed out'), 'LSGG 311050Z 24008KT CAVOK 12/05 Q1021']);

    expect($provider->metar('LSGG'))->toBe('')
        ->and($provider->metar('LSGG'))->toStartWith('LSGG')
        ->and($provider->calls)->toBe(2);
});

it('does not remember an AviationWeather request that failed', function (): void {
    $http = Mockery::mock(HttpClient::class);
    $http->shouldReceive('get')->twice()->andThrow(new RuntimeException('connection refused'));
    $provider = new AviationWeather($http);

    expect($provider->metar('LSGG'))->toBe('')
        ->and($provider->metar('LSGG'))->toBe('');
});
