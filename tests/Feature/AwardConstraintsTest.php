<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Enums\UserState;
use App\Services\Awards\PirepConstraints;
use App\Services\Awards\UserConstraints;
use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;

/**
 * @param  array<int, Constraint>    $constraints
 * @return array<string, Constraint>
 */
function byAttribute(array $constraints): array
{
    $keyed = [];

    foreach ($constraints as $constraint) {
        $keyed[$constraint->getAttribute()] = $constraint;
    }

    return $keyed;
}

test('denied user columns are absent', function (string $column): void {
    expect(byAttribute(UserConstraints::make()))->not->toHaveKey($column);
})->with([
    'primary key'      => 'id',
    'password'         => 'password',
    'api key'          => 'api_key',
    'remember token'   => 'remember_token',
    'airline fk'       => 'airline_id',
    'rank fk'          => 'rank_id',
    'opaque pirep ptr' => 'last_pirep_id',
    'free text notes'  => 'notes',
    'discord id'       => 'discord_id',
    'last ip'          => 'last_ip',
    'updated at'       => 'updated_at',
    'deleted at'       => 'deleted_at',
]);

test('denied pirep columns are absent', function (string $column): void {
    expect(byAttribute(PirepConstraints::make()))->not->toHaveKey($column);
})->with([
    'primary key'     => 'id',
    'user fk'         => 'user_id',
    'airline fk'      => 'airline_id',
    'aircraft fk'     => 'aircraft_id',
    'event fk'        => 'event_id',
    'opaque flight'   => 'flight_id',
    'route blob'      => 'route',
    'free text notes' => 'notes',
    'updated at'      => 'updated_at',
    'deleted at'      => 'deleted_at',
]);

test('non-denylisted columns survive', function (): void {
    expect(byAttribute(UserConstraints::make()))
        ->toHaveKeys(['callsign', 'name', 'email', 'country', 'flights', 'active', 'created_at'])
        ->and(byAttribute(PirepConstraints::make()))
        ->toHaveKeys(['flight_number', 'route_code', 'distance', 'score', 'block_on_time']);
});

test('varchar airport ids survive the foreign key rule', function (): void {
    expect(byAttribute(UserConstraints::make()))
        ->toHaveKeys(['home_airport_id', 'curr_airport_id'])
        ->and(byAttribute(PirepConstraints::make()))
        ->toHaveKeys(['dpt_airport_id', 'arr_airport_id', 'alt_airport_id']);
});

test('user column types map to the right constraint class', function (): void {
    $constraints = byAttribute(UserConstraints::make());

    expect($constraints['callsign'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['flights'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['flights']->isInteger())->toBeTrue()
        ->and($constraints['created_at'])->toBeInstanceOf(DateConstraint::class)
        ->and($constraints['active'])->toBeInstanceOf(BooleanConstraint::class)
        ->and($constraints['state'])->toBeInstanceOf(SelectConstraint::class);
});

test('pirep column types map to the right constraint class', function (): void {
    $constraints = byAttribute(PirepConstraints::make());

    expect($constraints['flight_number'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['score'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['score']->isInteger())->toBeTrue()
        ->and($constraints['distance'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['distance']->isInteger())->toBeFalse()
        ->and($constraints['submitted_at'])->toBeInstanceOf(DateConstraint::class)
        ->and($constraints['state'])->toBeInstanceOf(SelectConstraint::class);
});

test('nullable columns are marked nullable', function (): void {
    $constraints = byAttribute(UserConstraints::make());

    expect($constraints['callsign']->isNullable())->toBeTrue()
        ->and($constraints['email']->isNullable())->toBeFalse();
});

test('overrides supply enum select options', function (): void {
    $userState = byAttribute(UserConstraints::make())['state'];
    $pirepState = byAttribute(PirepConstraints::make())['state'];

    expect($userState->getOptions())
        ->toHaveKey(UserState::ACTIVE->value, UserState::ACTIVE->getLabel())
        ->and($pirepState->getOptions())
        ->toHaveKey(PirepState::ACCEPTED->value, PirepState::ACCEPTED->getLabel());
});

test('overrides supply options for every enum-backed pirep column', function (string $column): void {
    expect(byAttribute(PirepConstraints::make())[$column])
        ->toBeInstanceOf(SelectConstraint::class)
        ->and(byAttribute(PirepConstraints::make())[$column]->getOptions())
        ->not->toBeEmpty();
})->with(['state', 'status', 'source', 'sim_type', 'flight_type']);

/*
 * Design D3. Filament applies each rule as its own `whereHas`, so two dotted
 * rules about "a PIREP" can match two different PIREPs -- silently wrong.
 * The rule that prevents it is that no registered constraint is dotted.
 */
test('no constraint queries a relationship', function (array $constraints): void {
    foreach ($constraints as $constraint) {
        expect($constraint->getAttribute())->not->toContain('.')
            ->and($constraint->queriesRelationships())->toBeFalse();
    }

    expect($constraints)->not->toBeEmpty();
})->with([
    'users'  => UserConstraints::make(...),
    'pireps' => PirepConstraints::make(...),
]);

/*
 * `Constraint::model()` mutates and returns `$this` rather than a clone, so a
 * memoized set would be stamped with whichever model was compiled last.
 */
test('each call returns fresh constraint instances', function (): void {
    $first = byAttribute(UserConstraints::make());
    $second = byAttribute(UserConstraints::make());

    expect($first['state'])->not->toBe($second['state'])
        ->and($first['callsign'])->not->toBe($second['callsign'])
        ->and(byAttribute(PirepConstraints::make())['state'])
        ->not->toBe(byAttribute(PirepConstraints::make())['state']);
});
