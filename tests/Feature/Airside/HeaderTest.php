<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Contracts\Metar as MetarProvider;
use App\Features\Shell\Livewire\HeaderMetar;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * 6.4 and 6.5: the dispatch header's METAR states, the clocks and the flash toasts.
 */
function fakeMetarProvider(?string $metar = null, ?Throwable $exception = null): void
{
    app()->instance(MetarProvider::class, new class($metar, $exception) extends MetarProvider
    {
        public function __construct(private readonly ?string $metar, private readonly ?Throwable $exception) {}

        public function metar(string $icao): string
        {
            if ($this->exception instanceof Throwable) {
                throw $this->exception;
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

function metarObservedAt(int $timestamp): string
{
    return sprintf('KJFK %sZ 18010KT 10SM FEW020 20/10 A3000', gmdate('dHi', $timestamp));
}

beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
    $this->actingAs($this->pilot);
});

it('loads the raw METAR for the station', function (): void {
    $raw = metarObservedAt(time());
    fakeMetarProvider($raw);

    Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => 'KJFK'])
        ->assertViewHas('state', 'loaded')
        ->assertViewHas('metar', $raw);
});

it('reports a missing station', function (): void {
    Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => null])
        ->assertViewHas('state', 'missing-station');
});

it('reports a missing METAR when only a TAF came back', function (): void {
    fakeMetarProvider('');
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

    Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => 'KJFK'])
        ->assertViewHas('state', 'missing');
});

it('says the METAR is unavailable and retries', function (): void {
    fakeMetarProvider(exception: new RuntimeException('down'));

    $component = Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => 'KJFK'])
        ->assertViewHas('state', 'error');

    $raw = metarObservedAt(time());
    fakeMetarProvider($raw);

    $component->call('retry')->assertViewHas('state', 'loaded')->assertViewHas('metar', $raw);
});

it('marks a METAR older than ninety minutes as stale', function (): void {
    $observed = time() - (91 * 60);
    fakeMetarProvider(metarObservedAt($observed));

    Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => 'KJFK'])
        ->assertViewHas('state', 'stale');
});

it('keeps the station out of the client reach', function (): void {
    fakeMetarProvider(metarObservedAt(time()));

    Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => 'KJFK'])
        ->set('icao', 'KLAX');
})->throws(CannotUpdateLockedPropertyException::class);

it('escapes HTML in a flash before it reaches the toast, which renders with x-html', function (): void {
    $html = $this->withSession(['success' => 'Linked <b>Discord</b>'])->get('/airside-probe/pilot')->getContent();

    expect($html)->not->toContain("success('Linked <b>")
        ->not->toContain('Linked <b>Discord</b>');
});

it('shows a flash only once', function (): void {
    $this->followingRedirects()->get('/airside-probe/save');

    // A test process serves every request from one application; Octane (and
    // php-fpm) start the next request with the scoped bindings gone.
    app()->forgetScopedInstances();

    expect($this->get('/airside-probe/pilot')->getContent())->not->toContain('Saved the profile');
});

it('hides the dispatch status from guests but keeps the airline identity', function (): void {
    auth()->logout();

    $html = $this->get('/airside-probe')->getContent();

    expect($html)->not->toContain('aria-label="Dispatch status"')
        ->and($html)->toContain('aria-label="Airline identity"');
});
