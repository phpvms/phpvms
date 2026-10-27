<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->migration = require database_path('migrations/2026_10_28_000000_make_pages_path_unique.php');
    $this->migration->down();
});

it('keeps the lowest id on a duplicate path, clears the rest and adds the index', function (): void {
    $now = now();
    $row = fn (string $name, ?string $path): array => [
        'name'    => $name, 'slug' => $name, 'path' => $path, 'type' => 0, 'public' => 1,
        'enabled' => 1, 'status' => 0, 'created_at' => $now, 'updated_at' => $now,
    ];

    DB::table('pages')->insert([$row('a', '/about'), $row('b', '/about'), $row('c', ''), $row('d', null)]);

    $this->migration->up();

    expect(DB::table('pages')->orderBy('id')->pluck('path', 'name')->all())
        ->toBe(['a' => '/about', 'b' => null, 'c' => null, 'd' => null])
        ->and(Schema::hasIndex('pages', 'pages_path_unique'))->toBeTrue()
        ->and(Schema::hasIndex('pages', 'pages_path_index'))->toBeFalse();

    // Several pages without a path may still coexist.
    Page::factory()->count(2)->create();
});

it('is safe to run twice', function (): void {
    $this->migration->up();
    $this->migration->up();

    expect(Schema::hasIndex('pages', 'pages_path_unique'))->toBeTrue();
});
