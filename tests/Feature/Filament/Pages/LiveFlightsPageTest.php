<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Filament\Pages\LiveFlights;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepPosition;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

/**
 * A PIREP holding a position row, with both position timestamps pinned.
 */
function liveFlight(int $lastSeenMinutesAgo = 0, array $pirepAttrs = [], ?PirepPhase $phase = null): Pirep
{
    $pirep = Pirep::factory()->create($pirepAttrs);

    PirepPosition::factory()->create([
        'pirep_id' => $pirep->id,
        'user_id'  => $pirep->user_id,
        'phase'    => ($phase ?? PirepPhase::ENROUTE)->value,
    ]);

    $seenAt = now()->subMinutes($lastSeenMinutesAgo);

    PirepPosition::where('pirep_id', $pirep->id)->update([
        'created_at' => $seenAt->copy()->subHour(),
        'updated_at' => $seenAt,
    ]);

    return $pirep->fresh();
}

it('lists only the PIREPs holding a position row', function (): void {
    $onMap = liveFlight();
    $offMap = Pirep::factory()->create();

    Livewire::test(LiveFlights::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$onMap])
        ->assertCanNotSeeTableRecords([$offMap]);
});

it('does not N+1: adding flights does not add queries', function (): void {
    $countRenderQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(LiveFlights::class)->assertSuccessful();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    liveFlight();

    // Discard the first render: it warms the settings and permission caches,
    // which would otherwise show up as a difference between the two counts.
    $countRenderQueries();
    $oneFlight = $countRenderQueries();

    for ($i = 0; $i < 4; $i++) {
        liveFlight();
    }

    expect($countRenderQueries())->toBe($oneFlight);
});

it('orders flights stalest first', function (): void {
    $fresh = liveFlight(lastSeenMinutesAgo: 0);
    $stale = liveFlight(lastSeenMinutesAgo: 40);

    Livewire::test(LiveFlights::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$stale, $fresh], inOrder: true);
});

it('renders the phase and both timestamps from the position row', function (): void {
    $pirep = liveFlight(lastSeenMinutesAgo: 5, phase: PirepPhase::ARRIVED);
    $position = $pirep->position;

    Livewire::test(LiveFlights::class)
        ->assertSuccessful()
        ->assertTableColumnStateSet('phase', PirepPhase::ARRIVED->getLabel(), $pirep)
        ->assertTableColumnStateSet('last_seen', $position->updated_at->diffForHumans(), $pirep)
        ->assertTableColumnStateSet('on_map_since', $position->created_at->diffForHumans(), $pirep);
});

it('marks a flight stale once it passes livemap.idle_time', function (): void {
    setting_save('livemap.idle_time', 15);

    $stale = liveFlight(lastSeenMinutesAgo: 40);
    $fresh = liveFlight(lastSeenMinutesAgo: 1);

    Livewire::test(LiveFlights::class)
        ->assertSuccessful()
        ->assertTableColumnStateSet('stale', __('filament.live_flights.badge.stale'), $stale)
        ->assertTableColumnStateSet('stale', null, $fresh);
});

it('flags a finished-but-unfiled flight and leaves an enroute one alone', function (): void {
    $unfiled = liveFlight(
        pirepAttrs: ['state' => PirepState::IN_PROGRESS],
        phase: PirepPhase::ARRIVED,
    );

    $enroute = liveFlight(
        pirepAttrs: ['state' => PirepState::IN_PROGRESS],
        phase: PirepPhase::ENROUTE,
    );

    Livewire::test(LiveFlights::class)
        ->assertSuccessful()
        ->assertTableColumnStateSet('unfiled', __('filament.live_flights.badge.unfiled'), $unfiled)
        ->assertTableColumnStateSet('unfiled', null, $enroute);
});

it('purges only the position row, leaving the PIREP and its acars rows intact', function (): void {
    $pirep = liveFlight();
    Acars::factory()->count(3)->create([
        'pirep_id' => $pirep->id,
        'type'     => AcarsType::FLIGHT_PATH,
    ]);

    Livewire::test(LiveFlights::class)
        ->callAction(TestAction::make('purge')->table($pirep))
        ->assertNotified();

    assertDatabaseMissing('pirep_positions', ['pirep_id' => $pirep->id]);

    assertDatabaseHas('pireps', [
        'id'          => $pirep->id,
        'state'       => $pirep->state->value,
        'flight_time' => $pirep->flight_time,
    ]);

    expect(Acars::where('pirep_id', $pirep->id)->count())->toBe(3);
});

it('leaves other flights on the map when one is purged', function (): void {
    $target = liveFlight();
    $bystander = liveFlight();

    Livewire::test(LiveFlights::class)
        ->callAction(TestAction::make('purge')->table($target));

    assertDatabaseMissing('pirep_positions', ['pirep_id' => $target->id]);
    assertDatabaseHas('pirep_positions', ['pirep_id' => $bystander->id]);
});

it('drops a purged flight out of the live map read path', function (): void {
    $pirep = liveFlight();

    expect(Pirep::onLiveMap()->pluck('pireps.id'))->toContain($pirep->id);

    Livewire::test(LiveFlights::class)
        ->callAction(TestAction::make('purge')->table($pirep))
        ->assertCanNotSeeTableRecords([$pirep]);

    expect(Pirep::onLiveMap()->pluck('pireps.id'))->not->toContain($pirep->id);
});

it('badges the navigation with the number of flights on the map', function (): void {
    expect(LiveFlights::getNavigationBadge())->toBeNull();

    liveFlight();
    liveFlight();
    Pirep::factory()->create(); // not on the map — must not be counted

    expect(LiveFlights::getNavigationBadge())->toBe('2');
});

it('drops the navigation badge when a flight is purged off the map', function (): void {
    $pirep = liveFlight();

    expect(LiveFlights::getNavigationBadge())->toBe('1');

    Livewire::test(LiveFlights::class)
        ->callAction(TestAction::make('purge')->table($pirep));

    expect(LiveFlights::getNavigationBadge())->toBeNull();
});

it('denies access to a user without the view permission', function (): void {
    $this->actingAs(User::factory()->create());

    expect(LiveFlights::canAccess())->toBeFalse();
});
