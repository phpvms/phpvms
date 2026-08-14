<?php

use App\Cron\Nightly\ProcessUserAwards;
use App\Enums\AwardTrigger;
use App\Enums\PirepState;
use App\Enums\UserState;
use App\Events\CronNightly;
use App\Events\ProcessAward;
use App\Models\Award;
use App\Models\AwardRule;
use App\Models\Pirep;
use App\Models\User;
use App\Models\UserAward;
use App\Services\Awards\AwardExport;
use App\Services\Awards\AwardRunService;
use App\Services\Awards\Constraints\Operators\PirepOperator;
use App\Services\Awards\CriteriaCompilationFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Awards\Awards\PilotFlightAwards;

beforeEach(function (): void {
    loadYamlIntoDb('fleet');
});

function flightTimeRule(int $min): array
{
    return ['r1' => ['type' => 'flight_time', 'data' => ['operator' => 'isMin', 'settings' => ['number' => $min]]]];
}

function flightsRule(int $min): array
{
    return ['r1' => ['type' => 'flights', 'data' => ['operator' => 'isMin', 'settings' => ['number' => $min]]]];
}

/** A tree naming a constraint this install has never registered. */
function uncompilableRule(): array
{
    return ['r1' => ['type' => AwardRule::SNIPPET_PREFIX.'never-existed', 'data' => ['operator' => 'matches', 'settings' => []]]];
}

function heldBy(Award $award, User $user): int
{
    return UserAward::where(['user_id' => $user->id, 'award_id' => $award->id])->count();
}

// --- PIREP trigger -------------------------------------------------------

test('rules-based award is granted to a qualifying user on PIREP acceptance', function (): void {
    $award = Award::factory()->rules(flightTimeRule(6000), AwardTrigger::Pirep)->create(['active' => 1]);
    $qualifies = User::factory()->create(['flight_time' => 6200]);
    $doesNot = User::factory()->create(['flight_time' => 100]);

    event(new ProcessAward($qualifies));

    expect(heldBy($award, $qualifies))->toBe(1)
        // Narrowed to the accepting user: nobody else is swept up by the event.
        ->and(heldBy($award, $doesNot))->toBe(0);
});

test('rules-based award is not granted for a non-qualifying user', function (): void {
    $award = Award::factory()->rules(flightTimeRule(6000), AwardTrigger::Pirep)->create(['active' => 1]);
    $user = User::factory()->create(['flight_time' => 100]);

    event(new ProcessAward($user));

    expect(heldBy($award, $user))->toBe(0);
});

test('a user who already holds the award is not granted it twice', function (): void {
    $award = Award::factory()->rules(flightTimeRule(100), AwardTrigger::Pirep)->create(['active' => 1]);
    $user = User::factory()->create(['flight_time' => 6200]);
    UserAward::create(['user_id' => $user->id, 'award_id' => $award->id]);

    event(new ProcessAward($user));
    event(new ProcessAward($user));

    expect(heldBy($award, $user))->toBe(1);
});

test('inactive rules-based award is not evaluated on ProcessAward', function (): void {
    $award = Award::factory()->rules(flightTimeRule(6000), AwardTrigger::Pirep)->create(['active' => 0]);
    $user = User::factory()->create(['flight_time' => 6200]);

    event(new ProcessAward($user));

    expect(heldBy($award, $user))->toBe(0);
});

test('legacy class-based award still grants through the same ProcessAward event', function (): void {
    $award = Award::factory()->create([
        'ref_model_type'   => PilotFlightAwards::class,
        'ref_model_params' => 1,
        'active'           => 1,
    ]);
    $user = User::factory()->create(['flights' => 1]);

    event(new ProcessAward($user));

    expect(heldBy($award, $user))->toBe(1);
});

/*
 * Design D6: the criteria are about *this* landing, not about any landing the
 * pilot has ever made. `PirepService::accept()` sets `last_pirep_id` right
 * before firing the event, and that is the PIREP the listener binds.
 */
test('the triggering-PIREP scope restricts the award to the PIREP being accepted', function (): void {
    $award = Award::factory()->rules([
        'r1' => ['type' => 'pireps', 'data' => ['operator' => 'count', 'settings' => [
            PirepOperator::INNER_RULES_NAME            => ['i1' => ['type' => 'landing_rate', 'data' => ['operator' => 'isMin', 'settings' => ['number' => -200]]]],
            PirepOperator::COMPARISON_NAME             => 'atLeast',
            'count'                                    => 1,
            PirepOperator::TRIGGERING_PIREP_SCOPE_NAME => true,
        ]]],
    ], AwardTrigger::Pirep)->create(['active' => 1]);

    $user = User::factory()->create();

    $poor = Pirep::factory()->create(['user_id' => $user->id, 'state' => PirepState::ACCEPTED, 'landing_rate' => -800]);
    $smooth = Pirep::factory()->create(['user_id' => $user->id, 'state' => PirepState::ACCEPTED, 'landing_rate' => -120]);

    // The hard landing comes in first: the pilot has a qualifying PIREP on
    // file, but not *this* one.
    $user->update(['last_pirep_id' => $poor->id]);
    event(new ProcessAward($user->fresh()));

    expect(heldBy($award, $user))->toBe(0);

    $user->update(['last_pirep_id' => $smooth->id]);
    event(new ProcessAward($user->fresh()));

    expect(heldBy($award, $user))->toBe(1);
});

// --- Nightly user trigger -------------------------------------------------

test('nightly sweep grants a time-based award with no PIREP involved', function (): void {
    $award = Award::factory()->rules([
        'r1' => ['type' => 'created_at', 'data' => ['operator' => 'isBefore', 'settings' => [
            'mode' => 'absolute',
            'date' => now()->subYear()->toDateTimeString(),
        ]]],
    ], AwardTrigger::User)->create(['active' => 1]);

    $veteran = User::factory()->create(['state' => UserState::ACTIVE, 'created_at' => now()->subYears(2)]);
    $rookie = User::factory()->create(['state' => UserState::ACTIVE, 'created_at' => now()->subDays(3)]);

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect(heldBy($award, $veteran))->toBe(1)
        ->and(heldBy($award, $rookie))->toBe(0);
});

/*
 * The whole point of compiling to a query: the sweep must cost one question
 * per award however many pilots are on the roster.
 */
test('the nightly sweep asks one question per award, not one per user', function (): void {
    $award = Award::factory()->rules(flightsRule(5), AwardTrigger::User)->create(['active' => 1]);
    User::factory()->count(5)->create(['flights' => 10, 'state' => UserState::ACTIVE]);

    $selects = [];
    DB::listen(function ($query) use (&$selects): void {
        if (str_starts_with($query->sql, 'select') && str_contains(unquoteSql($query->sql), 'from users')) {
            $selects[] = $query->sql;
        }
    });

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect($selects)->toHaveCount(1)
        ->and(UserAward::where('award_id', $award->id)->count())->toBe(5);
});

test('nightly sweep does not evaluate pirep-triggered awards', function (): void {
    $award = Award::factory()->rules(flightsRule(0), AwardTrigger::Pirep)->create(['active' => 1]);
    $user = User::factory()->create(['flights' => 10, 'state' => UserState::ACTIVE]);

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect(heldBy($award, $user))->toBe(0);
});

test('nightly sweep skips a user who already holds every user-triggered award', function (): void {
    $award = Award::factory()->rules(flightsRule(5), AwardTrigger::User)->create(['active' => 1]);
    $user = User::factory()->create(['flights' => 10, 'state' => UserState::ACTIVE]);
    UserAward::create(['user_id' => $user->id, 'award_id' => $award->id]);

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect(heldBy($award, $user))->toBe(1);
});

test('nightly sweep excludes users in non-active states', function (): void {
    $award = Award::factory()->rules(flightsRule(5), AwardTrigger::User)->create(['active' => 1]);
    $user = User::factory()->create(['flights' => 10, 'state' => UserState::SUSPENDED]);

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect(heldBy($award, $user))->toBe(0);
});

test('inactive rules-based award is not evaluated by the nightly sweep', function (): void {
    $award = Award::factory()->rules(flightsRule(5), AwardTrigger::User)->create(['active' => 0]);
    $user = User::factory()->create(['flights' => 10, 'state' => UserState::ACTIVE]);

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect(heldBy($award, $user))->toBe(0);
});

// --- Failing closed -------------------------------------------------------

/*
 * The two ways an award can end up describing nobody. Both must grant nobody
 * -- and specifically not everybody, which is what a `users` query with its
 * criteria missing would return.
 */
test('an award whose criteria fail to compile grants nothing and reports it', function (): void {
    Log::spy();

    $award = Award::factory()->rules(uncompilableRule(), AwardTrigger::User)->create(['active' => 1]);
    User::factory()->count(3)->create(['flights' => 10, 'state' => UserState::ACTIVE]);

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect(UserAward::count())->toBe(0);

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message): bool => str_contains($message, $award->name)
    );
});

test('a compile failure surfaces to the run service rather than reading as nobody qualified', function (): void {
    $award = Award::factory()->rules(uncompilableRule(), AwardTrigger::User)->create(['active' => 1]);
    User::factory()->count(3)->create(['state' => UserState::ACTIVE]);

    expect(fn () => app(AwardRunService::class)->run($award))
        ->toThrow(CriteriaCompilationFailed::class);

    expect(UserAward::count())->toBe(0);
});

test('an award with no criteria at all grants nobody, not everybody', function (?array $tree): void {
    $award = Award::factory()->rules(flightsRule(1), AwardTrigger::User)->create(['active' => 1]);
    $award->saveConditionsTree($tree);

    User::factory()->count(3)->create(['flights' => 10, 'state' => UserState::ACTIVE]);

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect(app(AwardRunService::class)->run($award->fresh()))->toBeEmpty()
        ->and(UserAward::count())->toBe(0);
})->with([
    'empty tree'  => [[]],
    'no rule row' => [null],
]);

test('a compile failure on one award does not stop the sweep granting a later one', function (): void {
    Log::spy();

    $broken = Award::factory()->rules(uncompilableRule(), AwardTrigger::User)->create(['active' => 1]);
    $valid = Award::factory()->rules(flightsRule(5), AwardTrigger::User)->create(['active' => 1]);
    $user = User::factory()->create(['flights' => 10, 'state' => UserState::ACTIVE]);

    expect($broken->id)->toBeLessThan($valid->id);

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect(heldBy($broken, $user))->toBe(0)
        ->and(heldBy($valid, $user))->toBe(1);
});

// --- Import / export ------------------------------------------------------

test('an exported award round trips back in, inactive', function (): void {
    $original = Award::factory()->rules(flightTimeRule(6000), AwardTrigger::User)->create([
        'name'        => 'Six Thousand Hours',
        'description' => 'For the long haulers.',
        'active'      => 1,
    ]);

    $document = AwardExport::toJson($original);

    expect(json_decode($document, true))->toBe([
        'name'        => 'Six Thousand Hours',
        'description' => 'For the long haulers.',
        'image_url'   => null,
        'trigger'     => 'user',
        'conditions'  => flightTimeRule(6000),
    ]);

    $imported = AwardExport::fromJson($document);

    expect($imported->id)->not->toBe($original->id)
        ->and($imported->name)->toBe($original->name)
        ->and($imported->description)->toBe($original->description)
        ->and($imported->trigger)->toBe(AwardTrigger::User)
        ->and($imported->rule->conditions)->toBe($original->rule->conditions)
        ->and((bool) $imported->active)->toBeFalse()
        ->and($imported->ref_model_type)->toBeNull();
});

test('an imported award grants on the same criteria as the original once enabled', function (): void {
    $original = Award::factory()->rules(flightsRule(5), AwardTrigger::User)->create(['active' => 0]);

    $imported = AwardExport::fromJson(AwardExport::toJson($original));
    $imported->update(['active' => 1]);

    $user = User::factory()->create(['flights' => 10, 'state' => UserState::ACTIVE]);

    app(ProcessUserAwards::class)->handle(new CronNightly());

    expect(heldBy($imported, $user))->toBe(1)
        // Still inert until an admin enables it.
        ->and(heldBy($original, $user))->toBe(0);
});

test('a document that is not a readable award is refused', function (string $json): void {
    expect(fn (): Award => AwardExport::fromJson($json))->toThrow(InvalidArgumentException::class)
        ->and(Award::count())->toBe(0);
})->with([
    'not json'         => ['{nope'],
    'not an object'    => ['[1, 2, 3]'],
    'no name'          => ['{"conditions": {}}'],
    'no conditions'    => ['{"name": "Nameless"}'],
    'conditions wrong' => ['{"name": "Bad", "conditions": "everyone"}'],
]);
