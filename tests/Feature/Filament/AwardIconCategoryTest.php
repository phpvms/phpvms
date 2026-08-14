<?php

declare(strict_types=1);

use App\Filament\Resources\Awards\Pages\CreateAward;
use App\Filament\Resources\Awards\Pages\ListAwards;
use App\Filament\Resources\Awards\Schemas\AwardForm;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Award;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

it('searches the icon set and renders each match as its glyph', function (): void {
    $results = AwardForm::searchIcons('plane');

    expect($results)->toHaveKey('tabler-plane');
    // Substring, not prefix — "plane" should also surface `brand-planetscale`.
    expect(array_keys($results))->each->toContain('plane');
    expect($results['tabler-plane'])->toContain('<svg');
    expect($results['tabler-plane'])->toContain('plane');
});

// ~7200 icons is far more than a Select should be handed at once.
it('caps icon search results', function (): void {
    expect(AwardForm::searchIcons(''))->toHaveCount(50);
});

it('treats a space in an icon search as a hyphen', function (): void {
    expect(AwardForm::searchIcons('plane departure'))->toHaveKey('tabler-plane-departure');
});

// A term matching nothing is still offered, so a name from outside the Tabler
// set can be typed in. One that resolves to no glyph anywhere falls back to
// showing the bare name rather than throwing.
it('offers an unresolvable icon name as its bare name', function (): void {
    expect(AwardForm::searchIcons('zzzznotanicon'))->toBe(['zzzznotanicon' => 'zzzznotanicon']);
});

// The link is built as an HtmlString because helperText escapes plain strings —
// asserting on the raw attribute catches a regression to visible markup.
it('links to the icon set in a new tab from the icon field', function (): void {
    Livewire::test(CreateAward::class)
        ->assertSee('href="https://tabler.io/icons"', escape: false)
        ->assertSee('target="_blank"', escape: false)
        ->assertSee(__('filament.award_icon_browse'));
});

it('saves an icon and category on a new award', function (): void {
    Livewire::test(CreateAward::class)
        ->fillForm([
            'name'     => 'Long Haul',
            'icon'     => 'tabler-plane-departure',
            'category' => 'DISTANCE',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $award = Award::where('name', 'Long Haul')->first();

    expect($award->icon)->toBe('tabler-plane-departure');
    expect($award->category)->toBe('DISTANCE');
});

it('offers the presets alongside categories already in use', function (): void {
    Award::factory()->create(['category' => 'NIGHT OPS']);

    $options = Award::categoryOptions();

    expect($options)->toHaveKeys([...Award::CATEGORIES, 'NIGHT OPS']);
    // Keys and labels are the same string — a category is its own label.
    expect($options['NIGHT OPS'])->toBe('NIGHT OPS');
});

it('does not offer the same category twice when a preset is already in use', function (): void {
    Award::factory()->create(['category' => 'SKILL']);
    Award::factory()->create(['category' => 'SKILL']);

    $skill = array_filter(array_keys(Award::categoryOptions()), fn (string $key): bool => $key === 'SKILL');

    expect($skill)->toHaveCount(1);
});

// Uppercasing on the way in is what stops "Milestone" and "MILESTONE" becoming
// two entries in the list.
it('uppercases a category typed into the create-option form', function (): void {
    Livewire::test(CreateAward::class)
        ->callAction(
            TestAction::make('createOption')->schemaComponent('category'),
            ['category' => '  night ops  '],
        )
        ->assertHasNoActionErrors()
        ->assertSchemaStateSet(['category' => 'NIGHT OPS']);
});

// The dropdown has to look like a dropdown: opening it shows a shortlist rather
// than an empty box that reads as a broken text field.
it('shows a starter set of icons before anything is typed', function (): void {
    $starters = AwardForm::starterIcons();

    expect($starters)->toHaveKey('tabler-trophy');
    expect($starters)->toHaveCount(30);
    expect($starters['tabler-trophy'])->toContain('<svg');
});

// The point of uppercasing: the typed category has to come back as a suggestion
// rather than as a second, near-identical entry.
it('suggests a typed category once its award is saved', function (): void {
    Award::factory()->create(['category' => 'NIGHT OPS']);

    expect(Award::categoryOptions())->toHaveKey('NIGHT OPS');
});

it('offers a typed icon name that is not in the Tabler set', function (): void {
    $results = AwardForm::searchIcons('heroicon-o-star');

    expect($results)->toHaveKey('heroicon-o-star');
    // Heroicons ship with Filament, so this one resolves to a real glyph —
    // the typed name is not merely accepted, it renders.
    expect($results['heroicon-o-star'])->toContain('<svg');
});

it('filters the awards table by category', function (): void {
    $distance = Award::factory()->create(['category' => 'DISTANCE']);
    $skill = Award::factory()->create(['category' => 'SKILL']);

    Livewire::test(ListAwards::class)
        ->filterTable('category', 'DISTANCE')
        ->assertCanSeeTableRecords([$distance])
        ->assertCanNotSeeTableRecords([$skill]);
});

it('renders the pilot awards grid with icon, name and category', function (): void {
    $user = User::factory()->create();
    $award = Award::factory()->create([
        'name'     => 'Transatlantic',
        'icon'     => 'tabler-world',
        'category' => 'ROUTE',
    ]);

    $user->awards()->attach($award->id);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSee('Transatlantic')
        ->assertSee('ROUTE');
});

it('renders an award image in the grid when no icon is set', function (): void {
    $user = User::factory()->create();
    $award = Award::factory()->create([
        'name'      => 'Old Badge',
        'image_url' => 'https://example.com/badge.png',
        'icon'      => null,
    ]);

    $user->awards()->attach($award->id);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSee('https://example.com/badge.png');
});

it('survives an icon name that no longer exists in the icon set', function (): void {
    $user = User::factory()->create();
    $award = Award::factory()->create([
        'name' => 'Ghost Icon',
        'icon' => 'tabler-this-icon-was-removed',
    ]);

    $user->awards()->attach($award->id);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertOk()
        ->assertSee('Ghost Icon');
});

it('shows an empty state for a pilot with no awards', function (): void {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSee(__('filament.user_awards_empty'));
});
