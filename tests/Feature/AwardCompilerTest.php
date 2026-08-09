<?php

use App\Models\User;
use App\Services\Awards\CriteriaCompilationFailed;
use App\Services\Awards\CriteriaCompiler;
use Filament\Facades\Filament;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;

/**
 * A minimal constraint set, deliberately local to this file: the real
 * `UserConstraints` is generated from the schema and would make these
 * assertions depend on its denylist.
 *
 * @return array<int, Constraint>
 */
function compilerConstraints(): array
{
    return [
        TextConstraint::make('name'),
        NumberConstraint::make('flights')->integer(),
    ];
}

/**
 * @param array<string, mixed> $tree
 */
function compileTree(array $tree, ?CriteriaCompiler $compiler = null): Builder
{
    return ($compiler ?? new CriteriaCompiler())->compile(
        User::query(),
        $tree,
        compilerConstraints(),
        User::class,
    );
}

/**
 * flights >= 10 AND (name contains "alpha" OR name contains "bravo").
 *
 * @return array<string, mixed>
 */
function nestedTree(): array
{
    return [
        'r1' => ['type' => 'flights', 'data' => ['operator' => 'isMin', 'settings' => ['number' => 10]]],
        'r2' => [
            'type' => 'or',
            'data' => [
                'groups' => [
                    'g1' => ['rules' => ['g1r1' => ['type' => 'name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'alpha']]]]],
                    'g2' => ['rules' => ['g2r1' => ['type' => 'name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'bravo']]]]],
                ],
            ],
        ],
    ];
}

test('a nested AND/OR tree compiles to a nested query and returns only matching users', function (): void {
    $matches = User::factory()->create(['name' => 'alpha pilot', 'flights' => 20]);
    $alsoMatches = User::factory()->create(['name' => 'bravo pilot', 'flights' => 10]);
    User::factory()->create(['name' => 'alpha pilot', 'flights' => 9]);   // fails the AND leg
    User::factory()->create(['name' => 'charlie pilot', 'flights' => 50]); // fails the OR group

    $query = compileTree(nestedTree());

    // The OR group is a nested group inside the outer AND, not flattened.
    expect($query->toSql())->toMatch('/"flights" >= \?.*and \(\(.*\) or \(.*\)\)/s');

    expect($query->pluck('id')->sort()->values()->all())
        ->toBe(collect([$matches->id, $alsoMatches->id])->sort()->values()->all());
});

test('rule values are bound as parameters, including values containing SQL syntax', function (): void {
    $literal = User::factory()->create(['name' => "o'brien'); drop table users; --", 'flights' => 1]);
    User::factory()->create(['name' => 'someone else', 'flights' => 1]);

    $tree = [
        'r1' => ['type' => 'name', 'data' => ['operator' => 'contains', 'settings' => ['text' => "'); drop table users; --"]]],
    ];

    $query = compileTree($tree);

    // The value never reaches the SQL string; it is a placeholder plus a binding.
    expect($query->toSql())->not->toContain('drop table')
        ->and($query->getBindings())->toContain("%'); drop table users; --%");

    expect($query->pluck('id')->all())->toBe([$literal->id]);

    // And the table is still there afterwards.
    expect(User::query()->count())->toBe(2);
});

test('compilation works with no Livewire request and no table instance', function (): void {
    User::factory()->create(['name' => 'alpha pilot', 'flights' => 20]);

    expect(Livewire::isLivewireRequest())->toBeFalse()
        ->and(Filament::getCurrentPanel())->toBeNull();

    expect(compileTree(nestedTree())->count())->toBe(1);
});

test('a stored tree carrying its own item keys still compiles', function (): void {
    // Regression guard: `RuleBuilder` extends `Builder`, which regenerates item
    // keys on fill, so the compiler must apply `getState()` rather than the
    // stored array. Applying the stored array throws
    // "No query builder block found for [...]".
    $user = User::factory()->create(['name' => 'alpha pilot', 'flights' => 20]);

    $tree = [
        'my-stable-key'   => ['type' => 'flights', 'data' => ['operator' => 'isMin', 'settings' => ['number' => 10]]],
        'another-own-key' => ['type' => 'name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'alpha']]],
    ];

    expect(compileTree($tree)->pluck('id')->all())->toBe([$user->id]);
});

// An award's criteria run against `users`, so a query with criteria dropped
// matches EVERY user and grants the award to all of them. Compilation has to
// fail closed, which is the opposite of the vendor filter's own fail-safe.
test('a tree exceeding the rule limit refuses to compile', function (): void {
    User::factory()->count(3)->create(['name' => 'nobody', 'flights' => 0]);

    $tree = [];

    for ($i = 0; $i < 3; $i++) {
        $tree['r'.$i] = ['type' => 'flights', 'data' => ['operator' => 'isMin', 'settings' => ['number' => 10]]];
    }

    expect(fn (): Builder => compileTree($tree, new CriteriaCompiler(maxRules: 2)))
        ->toThrow(CriteriaCompilationFailed::class);
});

test('a tree exceeding the nesting depth limit refuses to compile', function (): void {
    User::factory()->count(2)->create(['name' => 'nobody', 'flights' => 0]);

    expect(fn (): Builder => compileTree(nestedTree(), new CriteriaCompiler(maxNestingDepth: 1)))
        ->toThrow(CriteriaCompilationFailed::class);
});
