<?php

declare(strict_types=1);

use App\Cron\Hourly\DeletePireps;
use App\Enums\AcarsType;
use App\Enums\PirepState;
use App\Events\CronHourly;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepPosition;
use App\Services\PirepService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite has no ALTER TABLE ADD CONSTRAINT, so the migration skips it there. A test
 * asserting an FK works, on a connection with no FK, is worse than no test.
 */
function acarsForeignKeyExists(): bool
{
    return collect(Schema::getForeignKeys('acars'))
        ->flatMap(fn (array $key): array => $key['columns'])
        ->contains('pirep_id');
}

function flightWithTelemetry(array $attrs = []): Pirep
{
    $pirep = Pirep::factory()->create($attrs);

    Acars::factory()->count(3)->create([
        'pirep_id' => $pirep->id,
        'type'     => AcarsType::FLIGHT_PATH,
    ]);

    PirepPosition::factory()->create([
        'pirep_id' => $pirep->id,
        'user_id'  => $pirep->user_id,
    ]);

    return $pirep;
}

function telemetryCount(Pirep $pirep): array
{
    return [
        'acars'    => DB::table('acars')->where('pirep_id', $pirep->id)->count(),
        'position' => DB::table('pirep_positions')->where('pirep_id', $pirep->id)->count(),
    ];
}

test('deleting a PIREP through the service removes its telemetry', function (): void {
    $pirep = flightWithTelemetry();

    expect(telemetryCount($pirep))->toBe(['acars' => 3, 'position' => 1]);

    app(PirepService::class)->delete($pirep);

    // The docblock has claimed `acars` since this method was written.
    expect(telemetryCount($pirep))->toBe(['acars' => 0, 'position' => 0])
        ->and(Pirep::withTrashed()->find($pirep->id))->toBeNull();
});

test('a soft-deleted PIREP keeps its telemetry', function (): void {
    $pirep = flightWithTelemetry();

    $pirep->delete();

    // The PIREP still exists and may be restored.
    expect(telemetryCount($pirep))->toBe(['acars' => 3, 'position' => 1])
        ->and(Pirep::withTrashed()->find($pirep->id))->not->toBeNull();
});

test('a soft-deleted PIREP is not counted as an orphan parent', function (): void {
    $pirep = flightWithTelemetry();
    $pirep->delete();

    // The migration's anti-join. Through Eloquent the SoftDeletes scope would hide
    // the parent and this would destroy live telemetry.
    $orphans = DB::table('acars')
        ->whereNotNull('acars.pirep_id')
        ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
            ->from('pireps')
            ->whereColumn('pireps.id', 'acars.pirep_id'))
        ->count();

    expect($orphans)->toBe(0);
});

test('deleting a PIREP outside the service still removes its telemetry', function (): void {
    if (!acarsForeignKeyExists()) {
        test()->markTestSkipped('The acars foreign key was skipped on this platform, so there is nothing to assert.');
    }

    $pirep = flightWithTelemetry();

    // Bypassing the service, the model and the observers.
    DB::table('pireps')->where('id', $pirep->id)->delete();

    expect(telemetryCount($pirep))->toBe(['acars' => 0, 'position' => 0]);
});

test('telemetry for a PIREP that does not exist is rejected', function (): void {
    if (!acarsForeignKeyExists()) {
        test()->markTestSkipped('The acars foreign key was skipped on this platform, so there is nothing to assert.');
    }

    expect(fn () => Acars::factory()->create([
        'pirep_id' => 'no-such-pirep-id',
        'type'     => AcarsType::FLIGHT_PATH,
    ]))->toThrow(QueryException::class);
});

test('the position row cascades on every platform', function (): void {
    // Declared at create time, so it exists everywhere including SQLite.
    $pirep = flightWithTelemetry();

    DB::table('pireps')->where('id', $pirep->id)->delete();

    expect(DB::table('pirep_positions')->where('pirep_id', $pirep->id)->count())->toBe(0);
});

test('the scheduled cancelled and rejected cleanup leaves no telemetry behind', function (): void {
    updateSetting('pireps.delete_cancelled_hours', 1);
    updateSetting('pireps.delete_rejected_hours', 1);

    $cancelled = flightWithTelemetry(['state' => PirepState::CANCELLED]);
    $rejected = flightWithTelemetry(['state' => PirepState::REJECTED]);

    foreach ([$cancelled, $rejected] as $pirep) {
        DB::table('pireps')->where('id', $pirep->id)
            ->update(['created_at' => Carbon::now('UTC')->subHours(5)]);
    }

    app(DeletePireps::class)->handle(new CronHourly());

    expect(telemetryCount($cancelled))->toBe(['acars' => 0, 'position' => 0])
        ->and(telemetryCount($rejected))->toBe(['acars' => 0, 'position' => 0]);
});

test('replacing a stored route still works under the constraint', function (): void {
    $pirep = Pirep::factory()->create();

    Acars::factory()->count(2)->create(['pirep_id' => $pirep->id, 'type' => AcarsType::ROUTE]);
    Acars::factory()->count(2)->create(['pirep_id' => $pirep->id, 'type' => AcarsType::FLIGHT_PATH]);

    // What route_post and saveRoute do. Unrelated to PIREP deletion.
    Acars::where('pirep_id', $pirep->id)->where('type', AcarsType::ROUTE)->delete();

    expect(Acars::where('pirep_id', $pirep->id)->where('type', AcarsType::ROUTE)->count())->toBe(0)
        ->and(Acars::where('pirep_id', $pirep->id)->flightPath()->count())->toBe(2);
});

test('clearing logs and flight path on a reused leg still works', function (): void {
    $pirep = Pirep::factory()->create();

    Acars::factory()->count(2)->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG]);
    Acars::factory()->count(2)->create(['pirep_id' => $pirep->id, 'type' => AcarsType::FLIGHT_PATH]);
    Acars::factory()->count(1)->create(['pirep_id' => $pirep->id, 'type' => AcarsType::ROUTE]);

    // What prefile does to a reused duplicate leg.
    Acars::where('pirep_id', $pirep->id)
        ->whereIn('type', [AcarsType::FLIGHT_PATH, AcarsType::LOG])
        ->delete();

    expect(Acars::where('pirep_id', $pirep->id)->count())->toBe(1)
        ->and(Pirep::find($pirep->id))->not->toBeNull();
});
