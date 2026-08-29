<?php

declare(strict_types=1);

use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Features\Tour\TourService;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\Awards\Constraints\Operators\TourCountOperator;
use App\Services\Awards\Constraints\Operators\TourOperator;
use App\Services\Awards\Constraints\TourConstraint;
use App\Services\Awards\CriteriaCompiler;
use App\Services\Awards\TourConstraints;
use App\Services\BidService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Compile a tree consisting solely of tour rules against `users`.
 *
 * @param  array<string, mixed> $tree
 * @return Builder<User>
 */
function compileTourTree(array $tree): Builder
{
    return new CriteriaCompiler()->compile(
        User::query(),
        $tree,
        [TourConstraint::make()],
        User::class,
    );
}

/**
 * One `tours` rule, wrapped in the tree shape the builder stores.
 *
 * @param  array<string, mixed> $settings
 * @return array<string, mixed>
 */
function tourRule(string $operator, array $settings): array
{
    return [
        'r1' => ['type' => 'tours', 'data' => ['operator' => $operator, 'settings' => $settings]],
    ];
}

/**
 * An inner rule over one `user_tours` column.
 *
 * @param  array<string, mixed> $settings
 * @return array<string, mixed>
 */
function tourInnerRule(string $key, string $column, string $operator, array $settings): array
{
    return [$key => ['type' => $column, 'data' => ['operator' => $operator, 'settings' => $settings]]];
}

/**
 * An operator carrying settings no form field would ever produce.
 *
 * @param array<string, mixed> $settings
 */
function tamperedTourOperator(TourOperator $operator, array $settings): TourOperator
{
    return $operator
        ->constraint(TourConstraint::make())
        ->settings($settings);
}

/**
 * Bid and fly every leg of a fresh tour for `$user`, so the real listener
 * chain (`PirepFiled` -> `TourService::advance()`) completes it exactly the
 * way the frontend would.
 */
function completeTour(int $legCount = 1, ?User $user = null, ?FlightBundle $bundle = null): UserTour
{
    $tour = makeTour($legCount, $user, $bundle);

    app(BidService::class)->addBid($tour['flights'][0], $tour['user'], $tour['aircraft']);

    foreach ($tour['flights'] as $flight) {
        fileTourLeg($tour['user'], $flight, $tour['aircraft']);
    }

    return $tour['user']->tours()->latest('id')->firstOrFail();
}

beforeEach(function (): void {
    tourSettingsBaseline();
});

test('a user who completed a tour matches "tours count is at least 1"; one who never ran one does not', function (): void {
    $qualifies = completeTour()->user;
    $neverRan = User::factory()->create();

    $tree = tourRule('count', [
        TourOperator::COMPARISON_NAME => 'atLeast',
        'count'                       => 1,
    ]);

    expect(compileTourTree($tree)->pluck('id')->all())->toBe([$qualifies->id]);
});

test('an in-progress run does not match, proving the forced completed scope', function (): void {
    $tour = makeTour(3);
    app(BidService::class)->addBid($tour['flights'][0], $tour['user'], $tour['aircraft']);
    fileTourLeg($tour['user'], $tour['flights'][0], $tour['aircraft']);

    $userTour = $tour['user']->tours()->latest('id')->firstOrFail();
    expect($userTour->status)->toBe(TourStatus::InProgress);

    $tree = tourRule('count', [
        TourOperator::COMPARISON_NAME => 'atLeast',
        'count'                       => 1,
    ]);

    expect(compileTourTree($tree)->count())->toBe(0);
});

test('a cancelled run does not match', function (): void {
    $tour = makeTour(3);
    app(BidService::class)->addBid($tour['flights'][0], $tour['user'], $tour['aircraft']);

    $userTour = $tour['user']->tours()->latest('id')->firstOrFail();
    app(TourService::class)->cancel($userTour);

    expect($userTour->fresh()->status)->toBe(TourStatus::Cancelled);

    $tree = tourRule('count', [
        TourOperator::COMPARISON_NAME => 'atLeast',
        'count'                       => 1,
    ]);

    expect(compileTourTree($tree)->count())->toBe(0);
});

test('the bundle_id inner rule discriminates one tour bundle from another', function (): void {
    $tourA = completeTour();
    completeTour();

    $tree = tourRule('count', [
        TourOperator::INNER_RULES_NAME => tourInnerRule('i1', 'bundle_id', 'is', ['value' => $tourA->bundle_id]),
        TourOperator::COMPARISON_NAME  => 'atLeast',
        'count'                        => 1,
    ]);

    expect(compileTourTree($tree)->pluck('id')->all())->toBe([$tourA->user_id]);
});

test('the comparison and its inverse map onto the right count operators', function (string $comparison, bool $inverse, array $expectedNames): void {
    $two = User::factory()->create(['name' => 'two']);
    $three = User::factory()->create(['name' => 'three']);

    foreach (range(1, 2) as $ignored) {
        completeTour(1, $two);
    }

    foreach (range(1, 3) as $ignored) {
        completeTour(1, $three);
    }

    $tree = tourRule($inverse ? 'count.inverse' : 'count', [
        TourOperator::COMPARISON_NAME => $comparison,
        'count'                       => 3,
    ]);

    expect(compileTourTree($tree)->pluck('name')->sort()->values()->all())->toBe($expectedNames);
})->with([
    'at least 3'    => ['atLeast', false, ['three']],
    'fewer than 3'  => ['atLeast', true, ['two']],
    'at most 3'     => ['atMost', false, ['three', 'two']],
    'more than 3'   => ['atMost', true, []],
    'exactly 3'     => ['exactly', false, ['three']],
    'not exactly 3' => ['exactly', true, ['two']],
]);

/*
 * Task-equivalent to the vendor's own discipline (`HasMinOperator::applyToBaseQuery`):
 * settings are re-checked where they are used, not merely where they are
 * entered. Driving the operator directly is the point -- hydrating a tree
 * first would have the form fields coerce the payload, which hides the guard
 * this asserts.
 */
test('a tampered setting applies nothing at apply time', function (TourOperator $operator): void {
    $query = $operator->applyToBaseQuery(User::query());

    expect($query->toSql())->toBe(User::query()->toSql())
        ->and($query->getBindings())->toBe([]);
})->with([
    'non-numeric count' => fn (): TourOperator => tamperedTourOperator(TourCountOperator::make(), [
        TourOperator::COMPARISON_NAME => 'atLeast',
        'count'                       => ['1'],
    ]),
    'unoffered comparison' => fn (): TourOperator => tamperedTourOperator(TourCountOperator::make(), [
        TourOperator::COMPARISON_NAME => '= 1) or 1=1 --',
        'count'                       => 1,
    ]),
]);

test('tour constraints exclude denied columns', function (string $column): void {
    $attributes = collect(TourConstraints::make())->map(fn ($constraint): string => $constraint->getAttribute());

    expect($attributes)->not->toContain($column);
})->with([
    'primary key'           => 'id',
    'opaque pirep ptr'      => 'pirep_id',
    'opaque flight ptr'     => 'flight_id',
    'free text description' => 'description',
    'legs json blob'        => 'legs',
    'updated at'            => 'updated_at',
]);

test('tour constraints include the bundle picker and status', function (): void {
    $attributes = collect(TourConstraints::make())->map(fn ($constraint): string => $constraint->getAttribute());

    expect($attributes)->toContain('bundle_id', 'status', 'name', 'legs_total', 'legs_completed', 'started_at', 'completed_at', 'created_at');
});
