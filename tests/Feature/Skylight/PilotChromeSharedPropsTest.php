<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Pirep;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

it('shares authenticated pilot identity and chrome', function (): void {
    $airline = Airline::factory()->create([
        'icao' => 'SKY',
        'iata' => 'SK',
        'name' => 'Skylight Air',
        'logo' => 'https://example.test/logo.svg',
    ]);
    $currentAirport = Airport::factory()->create(['icao' => 'KORD', 'timezone' => 'America/Chicago']);
    $user = User::factory()->create([
        'airline_id'      => $airline->id,
        'pilot_id'        => 42,
        'callsign'        => 'SKY42',
        'curr_airport_id' => $currentAirport->id,
    ]);

    $shared = sharedPropsFor($user);

    expect($shared['auth']['user'])->toMatchArray([
        'id'       => $user->id,
        'name'     => $user->name,
        'avatar'   => $user->resolveAvatarUrl(),
        'ident'    => $user->ident,
        'callsign' => 'SKY42',
        'airline'  => [
            'name' => 'Skylight Air',
            'icao' => 'SKY',
            'iata' => 'SK',
            'logo' => 'https://example.test/logo.svg',
        ],
    ])
        ->and(chromeFrom($shared))->toMatchArray([
            'activeSector' => null,
            'duty'         => ['state' => 'off_duty', 'label' => 'Off duty', 'color' => 'neutral'],
            'station'      => ['icao' => 'KORD', 'timezone' => 'America/Chicago'],
        ]);
});

it('shares null pilot chrome for unauthenticated requests', function (): void {
    $shared = sharedPropsFor();

    expect($shared['auth']['user'])->toBeNull()
        ->and(chromeFrom($shared))->toBeNull();
});

it('builds chrome without dashboard profile or PIREP page props', function (): void {
    $user = User::factory()->create();
    $shared = sharedPropsFor($user);

    expect($shared)->not->toHaveKey('dashboard')
        ->not->toHaveKey('profile')
        ->not->toHaveKey('pirep')
        ->and(chromeFrom($shared))->toMatchArray([
            'activeSector' => null,
            'duty'         => ['state' => 'off_duty', 'label' => 'Off duty', 'color' => 'neutral'],
            'station'      => null,
        ]);
});

it('uses an in-progress PIREP as the active sector and duty state', function (): void {
    $user = User::factory()->create();
    $pirep = Pirep::factory()->create([
        'user_id'        => $user->id,
        'state'          => PirepState::IN_PROGRESS,
        'dpt_airport_id' => 'KORD',
        'arr_airport_id' => 'KJFK',
    ]);

    $chrome = chromeFrom(sharedPropsFor($user));

    expect($chrome)->toMatchArray([
        'activeSector' => [
            'pirepId'       => $pirep->id,
            'ident'         => $pirep->load('airline')->ident,
            'departureIcao' => 'KORD',
            'arrivalIcao'   => 'KJFK',
            'state'         => 'in_progress',
        ],
        'duty' => ['state' => 'on_duty', 'label' => 'On duty', 'color' => 'success'],
    ]);
});

it('uses a paused PIREP as the active sector and duty state', function (): void {
    $user = User::factory()->create();
    $pirep = Pirep::factory()->create([
        'user_id' => $user->id,
        'state'   => PirepState::PAUSED,
    ]);

    $chrome = chromeFrom(sharedPropsFor($user));

    expect($chrome['activeSector']['pirepId'])->toBe($pirep->id)
        ->and($chrome['activeSector']['state'])->toBe('paused')
        ->and($chrome['duty'])->toBe([
            'state' => 'paused',
            'label' => 'Paused',
            'color' => 'warning',
        ]);
});

it('ignores saved bids and inactive PIREPs', function (): void {
    $user = User::factory()->create();
    Bid::factory()->create(['user_id' => $user->id]);
    Pirep::factory()->create(['user_id' => $user->id, 'state' => PirepState::PENDING]);

    $chrome = chromeFrom(sharedPropsFor($user));

    expect($chrome['activeSector'])->toBeNull()
        ->and($chrome['duty'])->toBe([
            'state' => 'off_duty',
            'label' => 'Off duty',
            'color' => 'neutral',
        ]);
});

it('uses the most recently updated active PIREP', function (): void {
    $user = User::factory()->create();
    $older = Pirep::factory()->create([
        'user_id'    => $user->id,
        'state'      => PirepState::IN_PROGRESS,
        'updated_at' => Carbon::parse('2026-08-10 10:00:00 UTC'),
    ]);
    $newer = Pirep::factory()->create([
        'user_id'    => $user->id,
        'state'      => PirepState::PAUSED,
        'updated_at' => Carbon::parse('2026-08-10 11:00:00 UTC'),
    ]);

    $chrome = chromeFrom(sharedPropsFor($user));

    expect($chrome['activeSector']['pirepId'])->toBe($newer->id)
        ->and($chrome['activeSector']['pirepId'])->not->toBe($older->id)
        ->and($chrome['duty']['state'])->toBe('paused');
});

it('uses the home airport when no current airport exists', function (): void {
    $homeAirport = Airport::factory()->create(['icao' => 'KDEN', 'timezone' => 'America/Denver']);
    $user = User::factory()->create(['home_airport_id' => $homeAirport->id]);

    expect(chromeFrom(sharedPropsFor($user))['station'])->toBe([
        'icao'     => 'KDEN',
        'timezone' => 'America/Denver',
    ]);
});

it('shares no station when the pilot has no airport', function (): void {
    $user = User::factory()->create([
        'curr_airport_id' => null,
        'home_airport_id' => null,
    ]);

    expect(chromeFrom(sharedPropsFor($user))['station'])->toBeNull();
});

it('preserves a station with no timezone', function (): void {
    $currentAirport = Airport::factory()->create(['icao' => 'EGLL', 'timezone' => null]);
    $user = User::factory()->create(['curr_airport_id' => $currentAirport->id]);

    expect(chromeFrom(sharedPropsFor($user))['station'])->toBe([
        'icao'     => 'EGLL',
        'timezone' => null,
    ]);
});

/** @return array<string, mixed> */
function sharedPropsFor(?User $user = null): array
{
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn (): ?User => $user);

    return app(HandleInertiaRequests::class)->share($request);
}

/** @param array<string, mixed> $shared */
function chromeFrom(array $shared): ?array
{
    $chrome = $shared['pilotChrome'];

    if (!$chrome instanceof Closure) {
        return null;
    }

    return $chrome()?->toArray();
}
