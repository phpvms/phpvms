<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Contracts\Metar as MetarProvider;
use App\Features\Shell\Livewire\HeaderMetar;
use App\Models\Airport;
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

it('shows a placeholder, then the raw METAR', function (): void {
    $raw = metarObservedAt(time());
    fakeMetarProvider($raw);

    Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => 'KJFK'])
        ->assertSee($raw)
        ->assertDontSee('Stale')
        ->assertDontSee('Loading METAR');
});

it('says there is no station', function (): void {
    Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => null])
        ->assertSee('No station');
});

it('says there is no METAR when only a TAF came back', function (): void {
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
        ->assertSee('No METAR');
});

it('says the METAR is unavailable and retries', function (): void {
    fakeMetarProvider(exception: new RuntimeException('down'));

    $component = Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => 'KJFK'])
        ->assertSee('METAR unavailable')
        ->assertSee('Retry');

    $raw = metarObservedAt(time());
    fakeMetarProvider($raw);

    $component->call('retry')->assertSee($raw)->assertDontSee('METAR unavailable');
});

it('marks a METAR older than ninety minutes as stale with its observation time', function (): void {
    $observed = time() - (91 * 60);
    fakeMetarProvider(metarObservedAt($observed));

    Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => 'KJFK'])
        ->assertSee('Stale · '.gmdate('H:i', $observed));
});

it('keeps the station out of the client reach', function (): void {
    fakeMetarProvider(metarObservedAt(time()));

    Livewire::withoutLazyLoading()->test(HeaderMetar::class, ['icao' => 'KJFK'])
        ->set('icao', 'KLAX');
})->throws(CannotUpdateLockedPropertyException::class);

it('loads the header METAR for the pilot station, lazily', function (): void {
    $airport = Airport::factory()->create(['icao' => 'KJFK', 'timezone' => 'America/New_York']);
    $this->pilot->update(['curr_airport_id' => $airport->id]);

    $response = $this->get('/airside-probe/pilot')->assertSeeLivewire(HeaderMetar::class);

    expect($response->getContent())->toContain('America')->toContain('New_York');
});

it('shows the toast tags from a redirect flash', function (): void {
    $html = $this->followingRedirects()->get('/airside-probe/save')->getContent();

    expect($html)->toContain('data-flash-success="Saved the profile"')
        ->not->toContain("interaction('toast').error(");
});

it('escapes HTML in a flash before it reaches the toast, which renders with x-html', function (): void {
    $html = $this->withSession(['success' => 'Linked <b>Discord</b>'])->get('/airside-probe/pilot')->getContent();

    expect($html)->not->toContain("success('Linked <b>")
        ->not->toContain('Linked <b>Discord</b>');
});

it('shows an error flash with the FAULT tag', function (): void {
    $html = $this->withSession(['error' => 'Something failed'])->get('/airside-probe/pilot')->getContent();

    expect($html)->toContain('data-flash-error="Something failed"');
});

it('shows a flash only once', function (): void {
    $this->followingRedirects()->get('/airside-probe/save');

    // A test process serves every request from one application; Octane (and
    // php-fpm) start the next request with the scoped bindings gone.
    app()->forgetScopedInstances();

    expect($this->get('/airside-probe/pilot')->getContent())->not->toContain('Saved the profile');
});

it('renders no toast when nothing was flashed', function (): void {
    expect($this->get('/airside-probe/pilot')->getContent())
        ->not->toContain("interaction('toast').success(")
        ->not->toContain("interaction('toast').error(");
});

it('hides the dispatch status from guests but keeps the airline identity', function (): void {
    auth()->logout();

    $html = $this->get('/airside-probe')->getContent();

    expect($html)->not->toContain('aria-label="Dispatch status"')
        ->and($html)->toContain('aria-label="Airline identity"');
});
