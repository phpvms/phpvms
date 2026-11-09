<?php

declare(strict_types=1);

use App\Features\Themes\ThemeFields;
use App\Features\Themes\ThemeOptions;
use App\Models\ThemeSetting;
use Filament\Forms\Components\CodeEditor;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    app(ThemeOptions::class)->register('airside', fn (): array => [
        CodeEditor::make('css')->default('base{}')->meta('emit', 'css'),
    ]);
});

it('reads the registered default when there is no row', function (): void {
    expect(app(ThemeFields::class)->get('airside', 'css'))->toBe('base{}');
});

it('prefers the theme row over the default and ignores a global row', function (): void {
    $fields = app(ThemeFields::class);

    ThemeSetting::factory()->create(['theme' => ThemeSetting::GLOBAL_THEME, 'name' => 'css', 'content' => 'global']);
    expect($fields->get('airside', 'css'))->toBe('base{}');

    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'own']);
    expect($fields->get('airside', 'css'))->toBe('own');
});

it('does not apply the global row to a theme without its own', function (): void {
    ThemeSetting::factory()->create(['theme' => ThemeSetting::GLOBAL_THEME, 'name' => 'css', 'content' => 'global']);
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'own']);

    expect(app(ThemeFields::class)->get('seven', 'css'))->toBeNull();
});

it('ignores inactive rows', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'off', 'active' => false]);

    expect(app(ThemeFields::class)->get('airside', 'css'))->toBe('base{}');
});

it('stores an override with set', function (): void {
    app(ThemeFields::class)->set('airside', 'css', ':root{--x:1}');

    expect(ThemeSetting::query()->where('theme', 'airside')->where('name', 'css')->value('content'))->toBe(':root{--x:1}');
});

it('stores a blank value that differs from the default', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'own']);

    app(ThemeFields::class)->set('airside', 'css', '');

    expect(ThemeSetting::query()->where('theme', 'airside')->where('name', 'css')->value('content'))->toBe('')
        ->and(app(ThemeFields::class)->get('airside', 'css'))->toBe('');
});

it('deletes the row when set receives null', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'own']);

    app(ThemeFields::class)->set('airside', 'css', null);

    expect(ThemeSetting::query()->count())->toBe(0);
});

it('deletes the row when set receives the default', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'own']);

    app(ThemeFields::class)->set('airside', 'css', 'base{}');

    expect(ThemeSetting::query()->count())->toBe(0);
});

it('deletes only the named row on reset', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css']);
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'other']);

    app(ThemeFields::class)->reset('airside', 'css');

    expect(ThemeSetting::query()->pluck('name')->all())->toBe(['other']);
});

it('returns null for a theme or field that registered nothing', function (): void {
    $fields = app(ThemeFields::class);

    expect($fields->get('no-such-theme', 'css'))->toBeNull()
        ->and($fields->get('airside', 'no-such-field'))->toBeNull();
});

it('falls back to the default and skips writes when the table is missing', function (): void {
    Schema::drop('theme_settings');

    $fields = app(ThemeFields::class);

    expect($fields->get('airside', 'css'))->toBe('base{}')
        ->and(fn () => $fields->set('airside', 'css', 'a{}'))->not->toThrow(Throwable::class)
        ->and(fn () => $fields->reset('airside', 'css'))->not->toThrow(Throwable::class);
});

it('returns a null override when there is no row', function (): void {
    expect(app(ThemeFields::class)->override('airside', 'css'))->toBeNull();
});

it('returns the theme row as the override', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'own']);

    expect(app(ThemeFields::class)->override('airside', 'css'))->toBe('own');
});

it('does not fall back to the global row for the override', function (): void {
    ThemeSetting::factory()->create(['theme' => ThemeSetting::GLOBAL_THEME, 'name' => 'css', 'content' => 'global']);

    expect(app(ThemeFields::class)->override('airside', 'css'))->toBeNull();
});

it('returns a null override when the table is missing', function (): void {
    Schema::drop('theme_settings');

    expect(app(ThemeFields::class)->override('airside', 'css'))->toBeNull();
});
