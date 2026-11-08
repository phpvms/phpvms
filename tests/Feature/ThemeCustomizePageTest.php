<?php

declare(strict_types=1);

use App\Features\Themes\ColorSchemeCss;
use App\Features\Themes\ThemeOptions;
use App\Filament\Pages\Theme;
use App\Models\Permission;
use App\Models\ThemeSetting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    updateSetting('general.theme', 'airside');

    config(['presets.color_schemes' => [
        'one' => ['name' => 'One', 'category' => 'Alpha', 'primary' => 'green', 'secondary' => 'lime', 'surface' => 'slate'],
        'two' => ['name' => 'Two', 'category' => 'Alpha', 'primary' => 'red', 'secondary' => 'lime', 'surface' => 'slate'],
    ]]);

    app(ThemeOptions::class)->register('airside', fn (string $theme): array => [
        Tab::make('CSS')->schema([
            ThemeOptions::colorSchemeSelect('one'),
            CodeEditor::make('style_css')
                ->language(Language::Css)
                ->default(':root{--color-primary-600:#000000;}')
                ->meta('emit', 'css'),
        ]),
        Tab::make('Layout')->schema([
            Select::make('layout')
                ->options(['sidebar' => 'Sidebar', 'topbar' => 'Top bar'])
                ->in(['sidebar', 'topbar'])
                ->default('sidebar')
                ->required(),
        ]),
    ]);
});

function customizeAdmin(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    return $user->fresh();
}

function storedTheme(string $name): ?string
{
    return ThemeSetting::query()->where('theme', 'airside')->where('name', $name)->value('content');
}

it('fills the registered fields from defaults and stored overrides', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'layout', 'content' => 'topbar']);
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)->assertFormSet(['style_css' => ':root{--color-primary-600:#000000;}', 'color_scheme' => 'one', 'layout' => 'topbar']);
});

it('stores a picked colour scheme and removes it again at the default', function (): void {
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    $component = livewire(Theme::class)
        ->fillForm(['color_scheme' => 'two'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedTheme('color_scheme'))->toBe('two');

    $component
        ->fillForm(['color_scheme' => 'one'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedTheme('color_scheme'))->toBeNull();
});

it('rejects an unknown colour scheme', function (): void {
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->fillForm(['color_scheme' => 'bogus'])
        ->call('save')
        ->assertHasFormErrors(['color_scheme']);

    expect(storedTheme('color_scheme'))->toBeNull();
});

it('keeps the preview scheme css in step with the picked scheme', function (): void {
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));
    $one = app(ColorSchemeCss::class)->render(config('presets.color_schemes.one'));
    $two = app(ColorSchemeCss::class)->render(config('presets.color_schemes.two'));

    livewire(Theme::class)
        ->assertSet('schemeCss', $one)
        ->set('data.color_scheme', 'two')
        ->assertSet('schemeCss', $two)
        ->set('data.color_scheme', 'bogus')
        ->assertSet('schemeCss', '');
});

it('stores a changed layout and keeps unchanged fields out of the table', function (): void {
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->fillForm(['layout' => 'topbar'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedTheme('layout'))->toBe('topbar')
        ->and(storedTheme('style_css'))->toBeNull();
});

it('removes the row when a value is saved back equal to its default', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'layout', 'content' => 'topbar']);
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->fillForm(['layout' => 'sidebar'])
        ->call('save');

    expect(ThemeSetting::query()->where('theme', 'airside')->count())->toBe(0);
});

it('stores an emptied field that has a default as a blank override', function (): void {
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->fillForm(['style_css' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedTheme('style_css'))->toBe('');
});

it('resets one field to its default and leaves the others', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'layout', 'content' => 'topbar']);
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'style_css', 'content' => 'a{}']);
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->callAction(TestAction::make('resetField')->schemaComponent('customize'), ['field' => 'layout'])
        ->assertFormSet(['layout' => 'sidebar', 'style_css' => 'a{}']);

    expect(storedTheme('layout'))->toBeNull()
        ->and(storedTheme('style_css'))->toBe('a{}');
});

it('rejects a select value outside its options and stores nothing', function (): void {
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->fillForm(['layout' => 'bogus'])
        ->call('save')
        ->assertHasFormErrors(['layout']);

    expect(ThemeSetting::query()->count())->toBe(0);
});

it('refreshes the preview scheme css when the colour scheme is reset', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'color_scheme', 'content' => 'two']);
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));
    $one = app(ColorSchemeCss::class)->render(config('presets.color_schemes.one'));
    $two = app(ColorSchemeCss::class)->render(config('presets.color_schemes.two'));

    livewire(Theme::class)
        ->assertSet('schemeCss', $two)
        ->callAction(TestAction::make('resetField')->schemaComponent('customize'), ['field' => 'color_scheme'])
        ->assertFormSet(['color_scheme' => 'one'])
        ->assertSet('schemeCss', $one);

    expect(storedTheme('color_scheme'))->toBeNull();
});

it('refuses to save or reset for a view-only admin', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'layout', 'content' => 'topbar']);
    $this->actingAs(customizeAdmin('view:theme'));

    livewire(Theme::class)
        ->call('save')
        ->assertForbidden();

    livewire(Theme::class)
        ->callAction(TestAction::make('resetField')->schemaComponent('customize'), ['field' => 'layout']);

    expect(storedTheme('layout'))->toBe('topbar');
});

it('refills the options from the newly selected theme', function (): void {
    updateSetting('general.theme', 'seven');
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'layout', 'content' => 'topbar']);
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)
        ->assertFormSet(['theme' => 'seven'])
        ->set('data.theme', 'airside')
        ->assertFormSet(['layout' => 'topbar', 'style_css' => ':root{--color-primary-600:#000000;}']);
});

it('fills, saves and resets a field registered under a dotted name', function (): void {
    app(ThemeOptions::class)->register('airside', fn (string $theme): array => [
        TextInput::make('appearance.accent')->default('#111111'),
    ]);
    $this->actingAs(customizeAdmin('view:theme', 'edit:theme'));

    livewire(Theme::class)->assertFormSet(['appearance.accent' => '#111111']);

    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'appearance.accent', 'content' => '#222222']);

    $component = livewire(Theme::class)->assertFormSet(['appearance.accent' => '#222222']);

    $component
        ->fillForm(['appearance.accent' => '#333333'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(storedTheme('appearance.accent'))->toBe('#333333');

    $component
        ->callAction(TestAction::make('resetField')->schemaComponent('customize'), ['field' => 'appearance.accent'])
        ->assertFormSet(['appearance.accent' => '#111111']);

    expect(storedTheme('appearance.accent'))->toBeNull();
});
