<?php

declare(strict_types=1);

use App\Models\ThemeSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('enforces the (theme, name) composite key', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'hero']);

    // Inside a savepoint: Postgres aborts the whole transaction on a failed
    // statement, and the test runs inside RefreshDatabase's.
    expect(fn () => DB::transaction(fn () => ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'hero'])))
        ->toThrow(QueryException::class);

    ThemeSetting::factory()->create(['theme' => 'seven', 'name' => 'hero']);

    expect(ThemeSetting::query()->count())->toBe(2);
});

it('updateOrCreate keeps one row per key', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'hero', 'content' => 'a']);

    ThemeSetting::query()->updateOrCreate(['theme' => 'airside', 'name' => 'hero'], ['content' => 'b']);

    expect(ThemeSetting::query()->count())->toBe(1)
        ->and(ThemeSetting::query()->first()->content)->toBe('b');
});

it('saves a fetched row without touching rows that share its name', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'hero', 'content' => 'a']);
    ThemeSetting::factory()->create(['theme' => 'seven', 'name' => 'hero', 'content' => 'a']);

    $row = ThemeSetting::query()->where('theme', 'airside')->where('name', 'hero')->firstOrFail();
    $row->content = 'changed';
    $row->save();

    expect(ThemeSetting::query()->where('theme', 'airside')->value('content'))->toBe('changed')
        ->and(ThemeSetting::query()->where('theme', 'seven')->value('content'))->toBe('a');
});

it('deletes only the fetched row', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'hero']);
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'footer']);
    ThemeSetting::factory()->create(['theme' => 'seven', 'name' => 'hero']);

    ThemeSetting::query()->where('theme', 'airside')->where('name', 'hero')->firstOrFail()->delete();

    expect(ThemeSetting::query()->count())->toBe(2)
        ->and(ThemeSetting::query()->where('theme', 'airside')->pluck('name')->all())->toBe(['footer'])
        ->and(ThemeSetting::query()->where('theme', 'seven')->count())->toBe(1);
});

it('scopes to a theme plus the global rows, and to active rows', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'a']);
    ThemeSetting::factory()->create(['theme' => ThemeSetting::GLOBAL_THEME, 'name' => 'b']);
    ThemeSetting::factory()->create(['theme' => 'seven', 'name' => 'c']);
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'd', 'active' => false]);

    expect(ThemeSetting::forTheme('airside')->pluck('name')->sort()->values()->all())->toBe(['a', 'b', 'd'])
        ->and(ThemeSetting::forTheme('airside')->active()->pluck('name')->sort()->values()->all())->toBe(['a', 'b']);
});
