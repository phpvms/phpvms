<?php

use App\Enums\UserState;
use App\Models\Award;
use App\Models\AwardRule;
use App\Models\AwardSnippet;
use App\Models\User;
use App\Services\Awards\Constraints\SnippetConstraint;
use App\Services\Awards\CriteriaCompilationFailed;
use App\Services\Awards\CriteriaCompiler;
use App\Services\Awards\SnippetConstraints;
use App\Services\Awards\UserConstraints;
use Illuminate\Database\Eloquent\Builder;

/**
 * A rule referencing a snippet by name, with the given operator.
 *
 * @return array{type: string, data: array<string, mixed>}
 */
function snippetRule(string $name, string $operator = 'matches'): array
{
    return [
        'type' => AwardRule::SNIPPET_PREFIX.$name,
        'data' => ['operator' => $operator, 'settings' => []],
    ];
}

/**
 * `state` is a select constraint, so its value lives under `values`.
 *
 * @return array<string, mixed>
 */
function stateIsActive(): array
{
    return [
        'r1' => ['type' => 'state', 'data' => ['operator' => 'is', 'settings' => ['values' => [UserState::ACTIVE->value]]]],
    ];
}

/**
 * Compile a tree the way the run paths will: the real user vocabulary plus
 * one constraint per saved snippet.
 *
 * @param array<string, mixed> $tree
 */
function compileWithSnippets(array $tree): Builder
{
    return new CriteriaCompiler()->compile(
        User::query(),
        $tree,
        [...UserConstraints::make(), ...SnippetConstraints::make()],
        User::class,
    );
}

test('a referenced snippet contributes its own conditions to the query', function (): void {
    AwardSnippet::factory()->create(['name' => 'active-pilot', 'conditions' => stateIsActive()]);

    $matches = User::factory()->create(['state' => UserState::ACTIVE, 'flights' => 20]);
    User::factory()->create(['state' => UserState::ACTIVE, 'flights' => 3]);      // fails the award's own rule
    User::factory()->create(['state' => UserState::SUSPENDED, 'flights' => 20]);  // fails the snippet

    $query = compileWithSnippets([
        'r1' => snippetRule('active-pilot'),
        'r2' => ['type' => 'flights', 'data' => ['operator' => 'isMin', 'settings' => ['number' => 10]]],
    ]);

    expect($query->pluck('id')->all())->toBe([$matches->id]);
});

test('the inverse operator negates the whole expanded fragment', function (): void {
    AwardSnippet::factory()->create(['name' => 'active-pilot', 'conditions' => stateIsActive()]);

    User::factory()->create(['state' => UserState::ACTIVE]);
    $suspended = User::factory()->create(['state' => UserState::SUSPENDED]);

    $query = compileWithSnippets(['r1' => snippetRule('active-pilot', 'matches.inverse')]);

    expect($query->toSql())->toContain('not (')
        ->and($query->pluck('id')->all())->toBe([$suspended->id]);
});

test('a snippet with an empty tree applies nothing', function (): void {
    AwardSnippet::factory()->create(['name' => 'empty', 'conditions' => []]);

    User::factory()->count(3)->create();

    $query = compileWithSnippets(['r1' => snippetRule('empty')]);

    expect($query->toSql())->toBe(User::query()->toSql())
        ->and($query->getBindings())->toBe([])
        ->and($query->count())->toBe(3);
});

test('a reference to a snippet that no longer exists refuses to compile', function (): void {
    // Reachable through import: a tree can name a snippet this install has
    // never had. The pivot's foreign key covers the delete path instead.
    //
    // This must fail closed. Dropping the reference would leave the award with
    // one fewer criterion, so it would match MORE users and grant itself to all
    // of them -- the loudest possible failure is the safe one.
    User::factory()->count(2)->create();

    expect(fn (): Builder => compileWithSnippets(['r1' => snippetRule('never-existed')]))
        ->toThrow(CriteriaCompilationFailed::class);
});

test('a self-referencing snippet stops at the depth limit instead of hanging', function (): void {
    AwardSnippet::factory()->create([
        'name'       => 'loop',
        'conditions' => [
            'r1' => snippetRule('loop'),
            'r2' => ['type' => 'flights', 'data' => ['operator' => 'isMin', 'settings' => ['number' => 10]]],
        ],
    ]);

    $matches = User::factory()->create(['flights' => 20]);
    User::factory()->create(['flights' => 1]);

    $query = compileWithSnippets(['r1' => snippetRule('loop')]);

    // Every level below the limit still contributes its `flights` rule; the
    // level that hits the limit contributes nothing, which is what ends it.
    expect(substr_count($query->toSql(), '"flights" >='))->toBe(SnippetConstraint::MAX_DEPTH)
        ->and($query->pluck('id')->all())->toBe([$matches->id]);
});

test('the delete guard names the awards referencing a snippet', function (): void {
    $snippet = AwardSnippet::factory()->create(['name' => 'active-pilot']);

    foreach (['First Solo', 'Long Hauler'] as $name) {
        Award::factory()->create(['name' => $name])->saveConditionsTree(['r1' => snippetRule('active-pilot')]);
    }

    expect($snippet->referencingAwardNames())->toBe(['First Solo', 'Long Hauler']);
});

test('an unreferenced snippet has no guard and deletes', function (): void {
    $snippet = AwardSnippet::factory()->create();

    expect($snippet->referencingAwardNames())->toBe([])
        ->and($snippet->delete())->toBeTrue();
});
