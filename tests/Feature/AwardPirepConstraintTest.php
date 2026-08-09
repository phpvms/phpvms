<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;
use App\Services\Awards\Constraints\Operators\PirepAggregateOperator;
use App\Services\Awards\Constraints\Operators\PirepCountOperator;
use App\Services\Awards\Constraints\Operators\PirepOperator;
use App\Services\Awards\Constraints\PirepConstraint;
use App\Services\Awards\CriteriaCompiler;
use Illuminate\Database\Eloquent\Builder;

/**
 * Compile a tree consisting solely of PIREP rules against `users`.
 *
 * @param  array<string, mixed> $tree
 * @return Builder<User>
 */
function compilePirepTree(array $tree, ?string $triggeringPirepId = null): Builder
{
    return new CriteriaCompiler()->compile(
        User::query(),
        $tree,
        [
            PirepConstraint::make()
                ->allowTriggeringPirepScope()
                ->triggeringPirep($triggeringPirepId),
        ],
        User::class,
    );
}

/**
 * One `pireps` rule, wrapped in the tree shape the builder stores.
 *
 * @param  array<string, mixed> $settings
 * @return array<string, mixed>
 */
function pirepRule(string $operator, array $settings): array
{
    return [
        'r1' => ['type' => 'pireps', 'data' => ['operator' => $operator, 'settings' => $settings]],
    ];
}

/**
 * An inner rule over one `pireps` column.
 *
 * @param  array<string, mixed> $settings
 * @return array<string, mixed>
 */
function innerRule(string $key, string $column, string $operator, array $settings): array
{
    return [$key => ['type' => $column, 'data' => ['operator' => $operator, 'settings' => $settings]]];
}

/**
 * An operator carrying settings no form field would ever produce.
 *
 * @param array<string, mixed> $settings
 */
function tamperedOperator(PirepOperator $operator, array $settings): PirepOperator
{
    return $operator
        ->constraint(PirepConstraint::make())
        ->settings($settings);
}

/**
 * PIREPs are expensive to fabricate (the factory makes an airline, a flight and
 * an aircraft each time), so tests share one user's worth at a time.
 */
function makePirep(User $user, array $attributes = []): Pirep
{
    return Pirep::factory()->create([
        'user_id' => $user->id,
        'state'   => PirepState::ACCEPTED,
        ...$attributes,
    ]);
}

test('a filtered count matches only users with enough PIREPs meeting every inner rule', function (): void {
    $qualifies = User::factory()->create();
    $tooFew = User::factory()->create();
    $wrongAirport = User::factory()->create();

    foreach (range(1, 3) as $ignored) {
        makePirep($qualifies, ['arr_airport_id' => 'KJFK', 'submitted_at' => '2026-03-01 00:00:00']);
        makePirep($wrongAirport, ['arr_airport_id' => 'KLAX', 'submitted_at' => '2026-03-01 00:00:00']);
    }

    // Right airport, but before the window.
    makePirep($qualifies, ['arr_airport_id' => 'KJFK', 'submitted_at' => '2025-01-01 00:00:00']);
    makePirep($tooFew, ['arr_airport_id' => 'KJFK', 'submitted_at' => '2026-03-01 00:00:00']);

    $tree = pirepRule('count', [
        PirepOperator::INNER_RULES_NAME => [
            ...innerRule('i1', 'arr_airport_id', 'equals', ['text' => 'KJFK']),
            ...innerRule('i2', 'submitted_at', 'isAfter', ['mode' => 'absolute', 'date' => '2026-01-01 00:00:00']),
        ],
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'count'                        => 3,
    ]);

    expect(compilePirepTree($tree)->pluck('id')->all())->toBe([$qualifies->id]);
});

test('a windowed sum aggregates only the PIREPs the inner rules select', function (): void {
    $qualifies = User::factory()->create();
    $staleHours = User::factory()->create();

    // 600 minutes inside the window.
    makePirep($qualifies, ['flight_time' => 300, 'submitted_at' => now()->subDays(3)]);
    makePirep($qualifies, ['flight_time' => 300, 'submitted_at' => now()->subDays(10)]);
    // Plenty of hours, but all of them outside it.
    makePirep($qualifies, ['flight_time' => 5000, 'submitted_at' => now()->subDays(200)]);

    makePirep($staleHours, ['flight_time' => 5000, 'submitted_at' => now()->subDays(200)]);
    makePirep($staleHours, ['flight_time' => 100, 'submitted_at' => now()->subDays(3)]);

    $tree = pirepRule('aggregate', [
        PirepOperator::INNER_RULES_NAME => innerRule('i1', 'submitted_at', 'isAfter', [
            'mode' => 'absolute',
            'date' => now()->subDays(30)->toDateTimeString(),
        ]),
        'aggregate'                    => 'sum',
        'column'                       => 'flight_time',
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'value'                        => 600,
    ]);

    $query = compilePirepTree($tree);

    // A correlated scalar subquery, not a `whereHas`, and the threshold is bound.
    expect($query->toSql())
        ->toContain('select cast(sum(pireps.flight_time)')
        ->toContain('from "pireps" where "pireps"."user_id" = "users"."id"')
        ->not->toContain('exists')
        ->and($query->getBindings())->toContain(600.0);

    expect($query->pluck('id')->all())->toBe([$qualifies->id]);
});

test('an unfiltered count counts every accepted PIREP the user has', function (): void {
    $qualifies = User::factory()->create();
    $tooFew = User::factory()->create();

    foreach (range(1, 2) as $ignored) {
        makePirep($qualifies);
    }

    makePirep($tooFew);

    $tree = pirepRule('count', [
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'count'                        => 2,
    ]);

    expect(compilePirepTree($tree)->pluck('id')->all())->toBe([$qualifies->id]);
});

test('the negated count excludes users with any matching PIREP', function (): void {
    $clean = User::factory()->create();
    $hasHardLanding = User::factory()->create();

    makePirep($clean, ['landing_rate' => -120]);
    makePirep($hasHardLanding, ['landing_rate' => -120]);
    makePirep($hasHardLanding, ['landing_rate' => -800]);

    // "at least 1 PIREP below -600 fpm", inverted -- i.e. none at all.
    $tree = pirepRule('count.inverse', [
        PirepOperator::INNER_RULES_NAME => innerRule('i1', 'landing_rate', 'isMax', ['number' => -600]),
        PirepOperator::COMPARISON_NAME  => 'atLeast',
        'count'                         => 1,
    ]);

    expect(compilePirepTree($tree)->pluck('id')->all())->toBe([$clean->id]);
});

test('an inner OR group nests inside the one subquery', function (): void {
    $jfk = User::factory()->create();
    $lax = User::factory()->create();
    $neither = User::factory()->create();

    makePirep($jfk, ['arr_airport_id' => 'KJFK']);
    makePirep($lax, ['arr_airport_id' => 'KLAX']);
    makePirep($neither, ['arr_airport_id' => 'KORD']);

    $tree = pirepRule('count', [
        PirepOperator::INNER_RULES_NAME => [
            'i1' => [
                'type' => 'or',
                'data' => [
                    'groups' => [
                        'g1' => ['rules' => innerRule('g1r1', 'arr_airport_id', 'equals', ['text' => 'KJFK'])],
                        'g2' => ['rules' => innerRule('g2r1', 'arr_airport_id', 'equals', ['text' => 'KLAX'])],
                    ],
                ],
            ],
        ],
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'count'                        => 1,
    ]);

    expect(compilePirepTree($tree)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$jfk->id, $lax->id])->sort()->values()->all());
});

test('the accepted-state scope is forced and a non-accepted PIREP never counts', function (): void {
    $accepted = User::factory()->create();
    $pending = User::factory()->create();

    makePirep($accepted, ['arr_airport_id' => 'KJFK']);
    makePirep($pending, ['arr_airport_id' => 'KJFK', 'state' => PirepState::PENDING]);

    $tree = pirepRule('count', [
        // The inner tree names the state itself; the forced scope still wins.
        PirepOperator::INNER_RULES_NAME => innerRule('i1', 'state', 'is', [
            'values' => [PirepState::PENDING->value, PirepState::ACCEPTED->value],
        ]),
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'count'                        => 1,
    ]);

    $query = compilePirepTree($tree);

    // The submitted state rule is applied, and the forced one is applied on top
    // of it -- it cannot be widened away.
    expect($query->toSql())->toContain('"pireps"."state" = ? and "pireps"."state" in (?, ?)')
        ->and($query->pluck('id')->all())->toBe([$accepted->id]);
});

test("another user's PIREPs never count towards the count or the aggregate", function (): void {
    $subject = User::factory()->create();
    $stranger = User::factory()->create();

    makePirep($stranger, ['flight_time' => 5000]);
    makePirep($subject, ['flight_time' => 100]);

    $countTree = pirepRule('count', [
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'count'                        => 2,
    ]);

    $aggregateTree = pirepRule('aggregate', [
        'aggregate'                    => 'sum',
        'column'                       => 'flight_time',
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'value'                        => 1000,
    ]);

    expect(compilePirepTree($countTree)->count())->toBe(0)
        ->and(compilePirepTree($aggregateTree)->pluck('id')->all())->toBe([$stranger->id]);
});

/*
 * Task 5.4, and the vendor's own discipline (`HasMinOperator::applyToBaseQuery`
 * lines 58-61): settings are re-checked where they are used, not merely where
 * they are entered. Driving the operator directly is the point -- hydrating a
 * tree first would have the form fields coerce the payload, which hides the
 * guard this asserts.
 */
test('a tampered setting applies nothing at apply time', function (PirepOperator $operator): void {
    $query = $operator->applyToBaseQuery(User::query());

    expect($query->toSql())->toBe(User::query()->toSql())
        ->and($query->getBindings())->toBe([]);
})->with([
    'non-numeric count' => fn (): PirepOperator => tamperedOperator(PirepCountOperator::make(), [
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'count'                        => ['1'],
    ]),
    'unoffered comparison' => fn (): PirepOperator => tamperedOperator(PirepCountOperator::make(), [
        PirepOperator::COMPARISON_NAME => '= 1) or 1=1 --',
        'count'                        => 1,
    ]),
    'non-numeric aggregate value' => fn (): PirepOperator => tamperedOperator(PirepAggregateOperator::make(), [
        'aggregate'                    => 'sum',
        'column'                       => 'flight_time',
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'value'                        => ['not', 'a', 'number'],
    ]),
    'unoffered aggregate function' => fn (): PirepOperator => tamperedOperator(PirepAggregateOperator::make(), [
        'aggregate'                    => 'count(*)) > 0 or (select 1',
        'column'                       => 'flight_time',
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'value'                        => 10,
    ]),
    'unregistered aggregate column' => fn (): PirepOperator => tamperedOperator(PirepAggregateOperator::make(), [
        'aggregate'                    => 'sum',
        'column'                       => 'flight_time) from users; --',
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'value'                        => 10,
    ]),
]);

/*
 * The parts of an aggregate that cannot be bound -- the function, the column
 * and the comparison -- come from server-side allowlists. A tampered value is
 * additionally replaced by the field's default before it ever reaches them, so
 * these settings survive as a valid query rather than applying nothing; what
 * matters is that the payload never reaches the SQL string.
 */
test('a tampered SQL-fragment setting never reaches the SQL string', function (array $tree, string $fragment): void {
    $query = compilePirepTree($tree);

    expect($query->toSql())->not->toContain($fragment)
        ->and($query->toSql())->not->toContain('drop table');

    // Still a runnable query, not a broken one.
    expect($query->count())->toBe(0);
})->with([
    'aggregate function' => [
        fn (): array => pirepRule('aggregate', [
            'aggregate'                    => 'count(*)) > 0 or (select 1',
            'column'                       => 'flight_time',
            PirepOperator::COMPARISON_NAME => 'atLeast',
            'value'                        => 10,
        ]),
        'select 1',
    ],
    'comparison' => [
        fn (): array => pirepRule('count', [
            PirepOperator::COMPARISON_NAME => '= 1 or 1=1 -- drop table users',
            'count'                        => 1,
        ]),
        '1=1',
    ],
]);

test('the triggering-PIREP scope narrows the subquery to that record', function (): void {
    $user = User::factory()->create();

    $poor = makePirep($user, ['landing_rate' => -800]);
    $smooth = makePirep($user, ['landing_rate' => -120]);

    $tree = pirepRule('count', [
        PirepOperator::INNER_RULES_NAME            => innerRule('i1', 'landing_rate', 'isMin', ['number' => -200]),
        PirepOperator::COMPARISON_NAME             => 'atLeast',
        'count'                                    => 1,
        PirepOperator::TRIGGERING_PIREP_SCOPE_NAME => true,
    ]);

    expect(compilePirepTree($tree, $smooth->id)->pluck('id')->all())->toBe([$user->id])
        ->and(compilePirepTree($tree, $poor->id)->count())->toBe(0);
});

test('a triggering-PIREP scope with no PIREP bound applies nothing at all', function (): void {
    $user = User::factory()->create();
    makePirep($user, ['landing_rate' => -120]);

    $tree = pirepRule('count', [
        PirepOperator::INNER_RULES_NAME            => innerRule('i1', 'landing_rate', 'isMin', ['number' => -200]),
        PirepOperator::COMPARISON_NAME             => 'atLeast',
        'count'                                    => 1,
        PirepOperator::TRIGGERING_PIREP_SCOPE_NAME => true,
    ]);

    // Fail closed: with nothing bound the rule would otherwise widen to every
    // PIREP the user has ever filed.
    $query = compilePirepTree($tree);

    expect($query->toSql())->toBe(User::query()->toSql())
        ->and($query->getBindings())->toBe([]);
});

test('a tree using the triggering-PIREP scope is detectable for save-time validation', function (): void {
    $scoped = pirepRule('count', [PirepOperator::TRIGGERING_PIREP_SCOPE_NAME => true, 'count' => 1]);
    $unscoped = pirepRule('count', ['count' => 1]);

    $nestedInOrGroup = [
        'r1' => [
            'type' => 'or',
            'data' => ['groups' => [
                'g1' => ['rules' => $unscoped],
                'g2' => ['rules' => $scoped],
            ]],
        ],
    ];

    expect(PirepConstraint::treeUsesTriggeringPirepScope($scoped))->toBeTrue()
        ->and(PirepConstraint::treeUsesTriggeringPirepScope($unscoped))->toBeFalse()
        ->and(PirepConstraint::treeUsesTriggeringPirepScope($nestedInOrGroup))->toBeTrue();
});

/*
 * The headline case, and the reason this constraint exists at all. Filament
 * applies each rule as its own `whereHas` (design D3), so a build with two
 * dotted PIREP rules would match user A -- who flew to KJFK once and landed
 * smoothly once, on different flights. Both conditions must describe the same
 * record.
 */
test('inner rules describe the same PIREP, not merely the same user', function (): void {
    $differentFlights = User::factory()->create();
    $oneGoodFlight = User::factory()->create();

    makePirep($differentFlights, ['arr_airport_id' => 'KJFK', 'landing_rate' => -800]);
    makePirep($differentFlights, ['arr_airport_id' => 'KLAX', 'landing_rate' => -120]);

    makePirep($oneGoodFlight, ['arr_airport_id' => 'KJFK', 'landing_rate' => -120]);

    $tree = pirepRule('count', [
        PirepOperator::INNER_RULES_NAME => [
            ...innerRule('i1', 'arr_airport_id', 'equals', ['text' => 'KJFK']),
            ...innerRule('i2', 'landing_rate', 'isMin', ['number' => -200]),
        ],
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'count'                        => 1,
    ]);

    $query = compilePirepTree($tree);

    expect($query->pluck('id')->all())->toBe([$oneGoodFlight->id]);

    // And the proof that it is one subquery rather than two: a single `exists`,
    // carrying both conditions.
    expect(substr_count($query->toSql(), 'exists'))->toBe(1)
        ->and($query->toSql())->toContain('"pireps"."arr_airport_id" like ? and "pireps"."landing_rate" >= ?');
});

test('the comparison and its inverse map onto the right count operators', function (string $comparison, bool $inverse, array $expectedNames): void {
    $two = User::factory()->create(['name' => 'two']);
    $three = User::factory()->create(['name' => 'three']);

    foreach (range(1, 2) as $ignored) {
        makePirep($two);
    }

    foreach (range(1, 3) as $ignored) {
        makePirep($three);
    }

    $tree = pirepRule($inverse ? 'count.inverse' : 'count', [
        PirepOperator::COMPARISON_NAME => $comparison,
        'count'                        => 3,
    ]);

    expect(compilePirepTree($tree)->pluck('name')->sort()->values()->all())->toBe($expectedNames);
})->with([
    'at least 3'    => ['atLeast', false, ['three']],
    'fewer than 3'  => ['atLeast', true, ['two']],
    'at most 3'     => ['atMost', false, ['three', 'two']],
    'more than 3'   => ['atMost', true, []],
    'exactly 3'     => ['exactly', false, ['three']],
    'not exactly 3' => ['exactly', true, ['two']],
]);

test('every operator produces a summary without blowing up', function (): void {
    $constraint = PirepConstraint::make();

    $count = PirepCountOperator::make()->constraint($constraint)->settings([
        PirepOperator::COMPARISON_NAME => 'atLeast',
        'count'                        => 10,
    ]);

    $aggregate = PirepAggregateOperator::make()->constraint($constraint)->settings([
        'aggregate'                    => 'sum',
        'column'                       => 'flight_time',
        PirepOperator::COMPARISON_NAME => 'atMost',
        'value'                        => 600,
    ]);

    expect($count->getSummary())->toContain('at least')
        ->and($aggregate->getSummary())->toContain('Sum')
        ->and($aggregate->getSummary())->toContain('Flight Time');
});
