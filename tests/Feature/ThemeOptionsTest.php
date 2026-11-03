<?php

declare(strict_types=1);

use App\Features\Themes\ThemeFields;
use App\Features\Themes\ThemeOptions;
use App\Models\ThemeSetting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Igaster\LaravelTheme\Facades\Theme;
use Igaster\LaravelTheme\Theme as ThemeDefinition;

enum ThemeOptionsTestLayout: string
{
    case Sidebar = 'sidebar';
    case Topbar = 'topbar';
}

/**
 * @return array<int, Tab>
 */
function layoutTabs(): array
{
    return [
        Tab::make('Layout')->schema([
            Select::make('layout')
                ->options(ThemeOptionsTestLayout::class)
                ->default(ThemeOptionsTestLayout::Sidebar),
        ]),
        Tab::make('Display')->schema([
            Toggle::make('show_clock')->default(true),
        ]),
    ];
}

it('has no tabs and no defaults for a theme with no registration', function (): void {
    $options = app(ThemeOptions::class);

    expect($options->tabsFor('seven'))->toBe([])
        ->and($options->defaults('seven'))->toBe([]);
});

it('builds an inherited registration with the child theme name and not for an unrelated theme', function (): void {
    $received = [];
    app(ThemeOptions::class)->register('airside', function (string $theme) use (&$received): array {
        $received[] = $theme;

        return layoutTabs();
    });

    new ThemeDefinition('airside-child', null, 'seven', Theme::find('airside'));

    $options = app(ThemeOptions::class);

    expect($options->tabsFor('airside-child'))->toHaveCount(2)
        ->and($received)->toBe(['airside-child'])
        ->and($options->tabsFor('seven'))->toBe([]);
});

it('prefers a child theme own registration over the parent one', function (): void {
    $options = app(ThemeOptions::class);
    $options->register('airside', fn (): array => layoutTabs());
    $options->register('airside-child', fn (): array => [Tab::make('Own')]);

    new ThemeDefinition('airside-child', null, 'seven', Theme::find('airside'));

    expect($options->tabsFor('airside-child'))->toHaveCount(1);
});

it('reads defaults and emit tags off-request from a form with nested tabs and hidden fields', function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => [
        Tabs::make()->tabs([
            Tab::make('CSS')->schema([
                Section::make('Styles')->schema([
                    CodeEditor::make('custom_css')->default('a{}')->meta('emit', 'css'),
                    TextInput::make('secret')->default('kept')->hidden()->meta('emit', 'footer'),
                ]),
            ]),
            Tab::make('Layout')->schema([
                Select::make('layout')->options(ThemeOptionsTestLayout::class)->default(ThemeOptionsTestLayout::Sidebar),
                CheckboxList::make('modes')->options(ThemeOptionsTestLayout::class)->default([ThemeOptionsTestLayout::Topbar]),
            ]),
        ]),
    ]);

    expect(app(ThemeOptions::class)->defaults('airside'))->toBe([
        'custom_css' => ['default' => 'a{}', 'emit' => 'css'],
        'secret'     => ['default' => 'kept', 'emit' => 'footer'],
        'layout'     => ['default' => 'sidebar', 'emit' => null],
        'modes'      => ['default' => ['topbar'], 'emit' => null],
    ]);
});

it('leaves a non-dehydrated field out of the defaults', function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => [
        ThemeOptions::colorSchemeSelect('custom_css'),
        CodeEditor::make('custom_css')->default('a{}')->meta('emit', 'css'),
    ]);

    expect(array_keys(app(ThemeOptions::class)->defaults('airside')))->toBe(['custom_css']);
});

it('leaves a hidden field with an explicit dehydrated(false) out of the defaults', function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => [
        TextInput::make('shown')->default('a'),
        TextInput::make('ui_only')->default('b')->hidden()->dehydrated(false),
    ]);

    expect(array_keys(app(ThemeOptions::class)->defaults('airside')))->toBe(['shown']);
});

it('returns the field default when no value is stored', function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => layoutTabs());
    Theme::set('airside');

    expect(theme_option('layout'))->toBe('sidebar')
        ->and(ThemeSetting::query()->count())->toBe(0);
});

it('returns a saved untagged option and leaves it out of the emit tags', function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => layoutTabs());
    Theme::set('airside');

    app(ThemeFields::class)->set('airside', 'layout', 'topbar');

    expect(theme_option('layout'))->toBe('topbar')
        ->and(app(ThemeOptions::class)->defaults('airside')['layout']['emit'])->toBeNull();
});

it('round trips a boolean option as a boolean', function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => layoutTabs());
    Theme::set('airside');

    app(ThemeFields::class)->set('airside', 'show_clock', false);

    expect(theme_option('show_clock'))->toBeFalse()
        ->and(ThemeSetting::query()->where('name', 'show_clock')->value('type'))->toBe('bool');
});

it('stores an array option and reads it back as an array', function (): void {
    app(ThemeFields::class)->set('airside', 'modes', ['a', 'b']);

    expect(app(ThemeFields::class)->get('airside', 'modes'))->toBe(['a', 'b']);
});

it('stores a cleared field whose default is not empty', function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => [CodeEditor::make('style_css')->default('a{}')]);

    app(ThemeFields::class)->set('airside', 'style_css', '');

    expect(ThemeSetting::query()->where('name', 'style_css')->exists())->toBeTrue()
        ->and(app(ThemeFields::class)->get('airside', 'style_css'))->toBe('');
});

it('removes the row when an enum select is saved at its default case', function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => layoutTabs());
    $fields = app(ThemeFields::class);

    $fields->set('airside', 'layout', 'topbar');

    expect(ThemeSetting::query()->count())->toBe(1);

    $fields->set('airside', 'layout', 'sidebar');

    expect(ThemeSetting::query()->count())->toBe(0);
});

it('resolves a default file from the parent theme when the child ships none', function (): void {
    new ThemeDefinition('airside-child', null, 'seven', Theme::find('airside'));

    $fields = app(ThemeFields::class);

    expect($fields->defaultFor('airside-child', 'styles.css'))->not->toBe('')
        ->and($fields->defaultFor('airside-child', 'styles.css'))->toBe($fields->defaultFor('airside', 'styles.css'));
});
