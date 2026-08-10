<?php

declare(strict_types=1);

use App\Enums\AwardTrigger;
use App\Filament\Resources\Awards\Pages\CreateAward;
use App\Filament\Resources\Awards\Pages\EditAward;
use App\Filament\Resources\Awards\Pages\ListAwards;
use App\Filament\Resources\Awards\Schemas\AwardForm;
use App\Filament\Resources\AwardSnippets\Pages\ManageAwardSnippets;
use App\Models\Award;
use App\Models\AwardSnippet;
use App\Models\User;
use App\Models\UserAward;
use App\Services\Awards\AwardExport;
use App\Services\Awards\AwardRunService;
use App\Services\Awards\Constraints\Operators\PirepOperator;
use App\Services\Awards\CriteriaCompilationFailed;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery\MockInterface;
use Modules\Awards\Awards\PilotFlightAwards;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

/**
 * One `users.flights` rule in the shape the rule builder stores.
 *
 * @return array<string, mixed>
 */
function flightsAtLeast(int $count): array
{
    return ['r1' => ['type' => 'flights', 'data' => ['operator' => 'isMin', 'settings' => ['number' => $count]]]];
}

/**
 * A PIREP rule narrowed to the PIREP whose acceptance triggered the award —
 * only legal under the `pirep` trigger.
 *
 * @return array<string, mixed>
 */
function triggeringPirepRule(): array
{
    return [
        'r1' => [
            'type' => 'pireps',
            'data' => [
                'operator' => 'count',
                'settings' => [
                    PirepOperator::COMPARISON_NAME             => 'atLeast',
                    'count'                                    => 1,
                    PirepOperator::TRIGGERING_PIREP_SCOPE_NAME => true,
                ],
            ],
        ],
    ];
}

/**
 * Stand in for the run service, which owns whether anybody is granted
 * anything. These cases only prove the page hands off correctly and reports
 * what comes back.
 *
 * @param Collection<int, User> $affected
 */
function fakeRunService(Collection $affected, ?bool $expectGrant = null): void
{
    test()->mock(AwardRunService::class, function (MockInterface $mock) use ($affected, $expectGrant): void {
        $mock->shouldReceive('run')
            ->once()
            ->withArgs(fn (Award $award, bool $grant = false): bool => $expectGrant === null || $grant === $expectGrant)
            ->andReturn($affected);
    });
}

it('creates an award without criteria', function (): void {
    Livewire::test(CreateAward::class)
        ->fillForm(['name' => 'Six Thousand Hours'])
        ->call('create')
        ->assertHasNoFormErrors();

    $award = Award::where('name', 'Six Thousand Hours')->first();

    expect($award)->not->toBeNull();
    expect($award->rule)->toBeNull();
    expect($award->ref_model_type)->toBeNull();
});

it('edits award info, type and trigger through the overview drawer', function (): void {
    $award = Award::factory()->create(['name' => 'Old Name', 'active' => true]);

    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->callAction('edit', [
            'name'    => 'New Name',
            'active'  => false,
            'trigger' => AwardTrigger::User->value,
        ])
        ->assertHasNoActionErrors();

    $award->refresh();

    expect($award->name)->toBe('New Name');
    expect((bool) $award->active)->toBeFalse();
    expect($award->trigger)->toBe(AwardTrigger::User);
});

it('switches a rules award to legacy through the drawer, clearing the ruleset', function (): void {
    $award = Award::factory()->rules()->create();

    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->callAction('edit', [
            'type'             => 'legacy',
            'ref_model_type'   => PilotFlightAwards::class,
            'ref_model_params' => '1',
        ])
        ->assertHasNoActionErrors();

    $award->refresh();

    expect($award->ref_model_type)->toBe(PilotFlightAwards::class);
    expect($award->rule)->toBeNull();
    expect($award->trigger)->toBeNull();
});

it('runs an award test and names who matches', function (): void {
    $award = Award::factory()->rules()->create(['active' => 1]);
    $match = User::factory()->create(['flights' => 500]);

    fakeRunService(collect([$match]), expectGrant: false);

    expect(AwardForm::runTestResults($award)->render())->toContain($match->name);
});

it('still saves an unchanged legacy award through the edit form', function (): void {
    $award = Award::factory()->create([
        'ref_model_type'   => PilotFlightAwards::class,
        'ref_model_params' => '1',
    ]);

    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    $award->refresh();

    expect($award->ref_model_type)->toBe(PilotFlightAwards::class);
    expect($award->ref_model_params)->toBe('1');
    expect($award->rule)->toBeNull();
    expect($award->trigger)->toBeNull();
});

it('saves the criteria tree built on the edit form', function (): void {
    $award = Award::factory()->create(['trigger' => AwardTrigger::User]);

    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->fillForm(['conditions' => flightsAtLeast(10)])
        ->call('save')
        ->assertHasNoFormErrors();

    $award->refresh();

    expect($award->rule)->not->toBeNull();

    $stored = array_values($award->rule->conditions);

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['type'])->toBe('flights')
        ->and($stored[0]['data']['settings']['number'])->toBe(10);
});

it('clearing every condition retires the ruleset rather than matching everyone', function (): void {
    $award = Award::factory()->create(['trigger' => AwardTrigger::User]);
    $award->saveConditionsTree(flightsAtLeast(10));

    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->fillForm(['conditions' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($award->refresh()->rule)->toBeNull();
});

it('renders the stored criteria when the award is reopened', function (): void {
    $award = Award::factory()->create(['trigger' => AwardTrigger::User]);
    $award->saveConditionsTree(flightsAtLeast(7));

    $state = Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->get('data')['conditions'];

    $rules = array_values($state);

    expect($rules)->toHaveCount(1)
        ->and($rules[0]['type'])->toBe('flights')
        ->and($rules[0]['data']['settings']['number'])->toEqual(7);
});

it('refuses a triggering-PIREP scope on a nightly award', function (): void {
    $award = Award::factory()->create(['trigger' => AwardTrigger::User]);
    $award->saveConditionsTree(triggeringPirepRule());

    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->call('save')
        ->assertHasFormErrors(['conditions']);
});

it('allows a triggering-PIREP scope on a PIREP-triggered award', function (): void {
    $award = Award::factory()->create(['trigger' => AwardTrigger::Pirep]);
    $award->saveConditionsTree(triggeringPirepRule());

    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    $stored = array_values($award->refresh()->rule->conditions);

    expect($stored[0]['data']['settings'][PirepOperator::TRIGGERING_PIREP_SCOPE_NAME])->toBeTrue();
});

it('reports the dry-run count and grants nothing', function (): void {
    $award = Award::factory()->rules()->create(['active' => 1]);

    fakeRunService(User::factory()->count(3)->create(), expectGrant: false);

    expect(AwardForm::runTestResults($award)->render())->toContain('3 pilots match')
        ->and(UserAward::count())->toBe(0);
});

it('runs now through the granting path', function (): void {
    $award = Award::factory()->rules()->create(['active' => 1]);

    fakeRunService(User::factory()->count(1)->create(), expectGrant: true);

    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->callAction('runNow')
        ->assertNotified(__('filament.award_run_affected', ['count' => 1]));
});

it('reports a compilation failure instead of a count', function (): void {
    $award = Award::factory()->rules()->create(['active' => 1]);

    $this->mock(AwardRunService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('run')->andThrow(CriteriaCompilationFailed::exceedsBounds(50, 5));
    });

    // Reported in the modal where the count would have been, not swallowed.
    expect(AwardForm::runTestResults($award)->render())
        ->toContain('Award criteria exceed the configured bounds');
});

it('creates a snippet, slugging the reference name from the label', function (): void {
    Livewire::test(ManageAwardSnippets::class)
        ->callAction('create', [
            'label'      => 'Active Pilot',
            'name'       => 'active-pilot',
            'conditions' => flightsAtLeast(1),
        ])
        ->assertHasNoActionErrors();

    $snippet = AwardSnippet::where('name', 'active-pilot')->first();

    expect($snippet)->not->toBeNull()
        ->and($snippet->label)->toBe('Active Pilot')
        ->and(array_values($snippet->conditions)[0]['type'])->toBe('flights');
});

it('keeps the reference name immutable once the snippet exists', function (): void {
    $snippet = AwardSnippet::factory()->create([
        'name'       => 'active-pilot',
        'label'      => 'Active Pilot',
        'conditions' => flightsAtLeast(1),
    ]);

    Livewire::test(ManageAwardSnippets::class)
        ->callAction(TestAction::make('edit')->table($snippet), [
            'name'  => 'renamed',
            'label' => 'Still Active',
        ])
        ->assertHasNoActionErrors();

    $snippet->refresh();

    expect($snippet->name)->toBe('active-pilot')
        ->and($snippet->label)->toBe('Still Active');
});

it('refuses to delete a snippet an award still references, naming the award', function (): void {
    $snippet = AwardSnippet::factory()->create(['name' => 'active-pilot']);

    $award = Award::factory()->create(['name' => 'Long Hauler', 'trigger' => AwardTrigger::User]);
    $award->saveConditionsTree(['r1' => ['type' => 'snippet:active-pilot', 'data' => ['operator' => 'matches', 'settings' => []]]]);

    Livewire::test(ManageAwardSnippets::class)
        ->callAction(TestAction::make('delete')->table($snippet))
        ->assertNotified(__('filament.award_snippet_delete_blocked'));

    expect(AwardSnippet::whereKey($snippet->getKey())->exists())->toBeTrue()
        ->and($snippet->referencingAwardNames())->toBe(['Long Hauler']);
});

it('deletes an unreferenced snippet', function (): void {
    $snippet = AwardSnippet::factory()->create();

    Livewire::test(ManageAwardSnippets::class)
        ->callAction(TestAction::make('delete')->table($snippet));

    expect(AwardSnippet::whereKey($snippet->getKey())->exists())->toBeFalse();
});

it('offers an import action on the awards list', function (): void {
    Livewire::test(ListAwards::class)
        ->assertSuccessful()
        ->assertActionExists('import');
});

// Asserting the action merely exists let a wrong AwardExport call sit here
// undetected, so drive a real document all the way through it.
it('imports an exported award document through the list action', function (): void {
    $source = Award::factory()->rules()->create(['name' => 'Century Club']);
    $document = AwardExport::toJson($source);
    $source->forceDelete();

    Livewire::test(ListAwards::class)
        ->callAction('import', ['document' => $document])
        ->assertHasNoActionErrors();

    $imported = Award::query()->where('name', 'Century Club')->firstOrFail();

    // `toEqual` rather than `toBe`: MySQL's native JSON type normalises object
    // key order on the way in, so the round trip returns the same pairs in a
    // different order.
    expect($imported->active)->toBeFalsy()
        ->and($imported->rule?->conditions)->toEqual($source->rule?->conditions);
});

/*
 * A tree already in the database that the rule builder cannot read -- written
 * before the current format, or by a direct write. Import now refuses these,
 * but rows that predate that check still have to open rather than take the
 * whole awards screen down with a 500.
 */
it('opens the list and the edit page when a stored tree is unreadable', function (): void {
    $award = Award::factory()->create(['trigger' => AwardTrigger::User]);

    DB::table('award_rules')->insert([
        'award_id'   => $award->id,
        'conditions' => json_encode(['combinator' => 'and', 'rules' => [['field' => 'flight_time']]], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(ListAwards::class)->assertSuccessful();
    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])->assertSuccessful();
});

/*
 * Switching an existing rule to the aggregate operator leaves the item's
 * settings without an `aggregate` key. The select renders its first option
 * anyway (`selectablePlaceholder(false)`), so the admin sees "Sum" chosen and
 * has no reason to touch it -- then saving fails "aggregate is required" on a
 * field that visibly has a value.
 */
it('treats the aggregate select as filled when it displays its default', function (): void {
    $award = Award::factory()->create(['trigger' => AwardTrigger::User]);
    $award->saveConditionsTree(['r1' => ['type' => 'pireps', 'data' => [
        'operator' => 'aggregate',
        // Neither `aggregate` nor `comparison` is stored; both selects show a
        // default the admin never picked, and both are `required()`.
        'settings' => [
            'column' => 'flight_time',
            'value'  => 3000,
        ],
    ]]]);

    Livewire::test(EditAward::class, ['record' => $award->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    // And the option the admin was shown is the one that got stored.
    $settings = array_values($award->refresh()->rule->conditions)[0]['data']['settings'];

    expect($settings['aggregate'])->toBe('sum')
        ->and($settings[PirepOperator::COMPARISON_NAME])->toBe('atLeast');
});

it('reports a malformed import document instead of creating an award', function (): void {
    $before = Award::query()->count();

    Livewire::test(ListAwards::class)
        ->callAction('import', ['document' => 'not json at all']);

    expect(Award::query()->count())->toBe($before);
});

/*
 * Valid JSON carrying a tree the rule builder cannot hydrate. Storing one
 * strands the award on a 500 edit page, because Filament's Builder fatals
 * rather than skipping the node it cannot read.
 */
it('rejects an import whose conditions tree the rule builder cannot read', function (array $conditions): void {
    $before = Award::query()->count();

    Livewire::test(ListAwards::class)
        ->callAction('import', ['document' => json_encode([
            'name'       => 'Unreadable',
            'conditions' => $conditions,
        ], JSON_THROW_ON_ERROR)]);

    expect(Award::query()->count())->toBe($before);
})->with([
    // The shape the discarded react-querybuilder design wrote.
    'foreign tree'         => fn (): array => ['combinator' => 'and', 'rules' => [['field' => 'flight_time']]],
    'item is not an array' => fn (): array => ['r1' => 'flight_time'],
    'item has no type'     => fn (): array => ['r1' => ['data' => ['operator' => 'isMin']]],
    'nested item is junk'  => fn (): array => ['r1' => ['type' => 'or', 'data' => ['groups' => [['rules' => ['n1' => 'nope']]]]]],
]);
