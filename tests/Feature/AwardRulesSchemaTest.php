<?php

use App\Enums\AwardTrigger;
use App\Models\Award;
use App\Models\AwardRule;
use App\Models\AwardSnippet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * A tree referencing the given snippets, one of them nested inside an OR
 * group so the walk has to recurse to find it.
 *
 * @param  list<string>         $names
 * @return array<string, mixed>
 */
function snippetTree(array $names): array
{
    $rules = [];

    foreach (array_values($names) as $i => $name) {
        $reference = ['type' => AwardRule::SNIPPET_PREFIX.$name, 'data' => []];

        $rules['r'.$i] = $i === 0 ? $reference : [
            'type' => 'or',
            'data' => ['groups' => [['rules' => ['n'.$i => $reference]]]],
        ];
    }

    return $rules;
}

test('rules-based award factory state persists the ruleset and trigger', function (): void {
    $award = Award::factory()->rules()->create();

    expect($award->rule->conditions)->toBe([
        'r1' => ['type' => 'flight_time', 'data' => ['operator' => 'isMin', 'settings' => ['number' => 100]]],
    ])
        ->and($award->trigger)->toBe(AwardTrigger::Pirep)
        ->and($award->isRulesBased())->toBeTrue();
});

test('legacy award factory has isRulesBased false', function (): void {
    $award = Award::factory()->create();

    expect($award->rule)->toBeNull()
        ->and($award->isRulesBased())->toBeFalse();
});

test('clearing a ruleset removes its award_rules row', function (): void {
    $award = Award::factory()->rules()->create();

    $award->saveConditionsTree(null);

    expect($award->refresh()->rule)->toBeNull()
        ->and($award->isRulesBased())->toBeFalse();
});

test('the snippet tables have the expected shape', function (): void {
    expect(Schema::hasTable('award_facts'))->toBeFalse()
        ->and(Schema::hasTable('award_rule_fact'))->toBeFalse()
        ->and(Schema::hasColumns('award_snippets', [
            'id', 'name', 'label', 'description', 'conditions', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('award_rule_snippet', ['award_rule_id', 'award_snippet_id']))->toBeTrue();

    // The slug is the referencing key, so it has to be unique.
    AwardSnippet::factory()->create(['name' => 'active-pilot']);

    expect(fn () => AwardSnippet::factory()->create(['name' => 'active-pilot']))
        ->toThrow(QueryException::class);
});

test('saving a tree mirrors its snippet references into the pivot', function (): void {
    $award = Award::factory()->create();
    $snippets = AwardSnippet::factory()->count(3)->sequence(
        ['name' => 'active-pilot'],
        ['name' => 'long-haul'],
        ['name' => 'unused'],
    )->create();

    $award->saveConditionsTree(snippetTree(['active-pilot', 'long-haul']));

    expect($award->rule->snippets->pluck('name')->sort()->values()->all())
        ->toBe(['active-pilot', 'long-haul'])
        ->and($snippets->last()->rules)->toBeEmpty();
});

test('editing a tree to drop a reference removes the pivot row', function (): void {
    $award = Award::factory()->create();
    AwardSnippet::factory()->count(2)->sequence(
        ['name' => 'active-pilot'],
        ['name' => 'long-haul'],
    )->create();

    $award->saveConditionsTree(snippetTree(['active-pilot', 'long-haul']));
    $award->saveConditionsTree(snippetTree(['long-haul']));

    expect($award->rule->fresh()->snippets->pluck('name')->all())->toBe(['long-haul']);
});

test('a referenced snippet cannot be deleted', function (): void {
    $award = Award::factory()->create();
    $snippet = AwardSnippet::factory()->create(['name' => 'active-pilot']);

    $award->saveConditionsTree(snippetTree(['active-pilot']));

    expect(fn () => $snippet->delete())->toThrow(QueryException::class);

    // ...but deleting the ruleset releases it.
    $award->saveConditionsTree(null);

    expect($snippet->delete())->toBeTrue();
});
