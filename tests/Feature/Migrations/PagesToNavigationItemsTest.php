<?php

declare(strict_types=1);

use App\Enums\NavigationTarget;
use App\Enums\PageStatus;
use App\Models\NavigationItem;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

function pagesToNavigationMigration(): object
{
    return require base_path('database/migrations_data/2026_08_31_000000_pages_to_navigation_items.php');
}

/**
 * The nav as `PageLinksComposer` built it, reduced to what a visitor could tell
 * apart: the label, where it goes, and whether it opens a new window.
 *
 * @return list<array{label: string, url: string|null, new_window: bool}>
 */
function legacyNavOutput(bool $authenticated): array
{
    $where = ['enabled' => true];

    if (!$authenticated) {
        $where['public'] = true;
    }

    return Page::where($where)->get()
        ->map(fn (Page $page): array => [
            'label'      => $page->name,
            'url'        => $page->url,
            'new_window' => $page->new_window,
        ])
        ->all();
}

/**
 * @return list<array{label: string, url: string|null, new_window: bool}>
 */
function migratedNavOutput(bool $authenticated): array
{
    return NavigationItem::query()
        ->visibleTo($authenticated)
        ->ordered()
        ->get()
        ->map(fn (NavigationItem $item): array => [
            'label'      => $item->label,
            'url'        => $item->resolveUrl(),
            'new_window' => $item->new_window,
        ])
        ->all();
}

/** A spread wide enough that a wrong mapping shows up somewhere. */
function seedLegacyPages(): void
{
    Page::factory()->create(['name' => 'About Us', 'slug' => 'about-us', 'public' => true, 'enabled' => true]);
    Page::factory()->create(['name' => 'Members Only', 'slug' => 'members', 'public' => false, 'enabled' => true]);
    Page::factory()->create(['name' => 'Retired', 'slug' => 'retired', 'public' => true, 'enabled' => false]);
    Page::factory()->link('https://discord.gg/example')->create([
        'name' => 'Discord', 'slug' => 'discord', 'public' => true, 'enabled' => true, 'new_window' => true,
    ]);
    Page::factory()->link('https://forum.example.test')->create([
        'name' => 'Forum', 'slug' => 'forum', 'public' => false, 'enabled' => true,
    ]);
}

it('reproduces the legacy navigation exactly, for a guest and for a member', function (bool $authenticated): void {
    seedLegacyPages();

    $before = legacyNavOutput($authenticated);

    pagesToNavigationMigration()->up();

    expect(migratedNavOutput($authenticated))->toBe($before)
        // Guard against the assertion passing because both sides are empty.
        ->and($before)->not->toBeEmpty();
})->with([
    'guest'  => false,
    'member' => true,
]);

it('preserves the order pages came back in', function (): void {
    seedLegacyPages();

    $before = array_column(legacyNavOutput(true), 'label');

    pagesToNavigationMigration()->up();

    expect(array_column(migratedNavOutput(true), 'label'))->toBe($before);
});

it('marks existing page content as published so it stays reachable', function (): void {
    $page = Page::factory()->create(['body' => '<p>Existing content</p>', 'draft_body' => null]);

    expect(Page::live()->count())->toBe(0);

    pagesToNavigationMigration()->up();

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->body)->toBe('<p>Existing content</p>')
        // The draft starts as a copy, so opening the editor does not show a blank page.
        ->and($page->draft_body)->toBe('<p>Existing content</p>')
        ->and(Page::live()->whereKey($page->id)->exists())->toBeTrue();
});

it('does not publish link rows', function (): void {
    $link = Page::factory()->link()->create();

    pagesToNavigationMigration()->up();

    expect($link->refresh()->status)->toBe(PageStatus::DRAFT);
});

it('points a link row at its URL and a page row at its page', function (): void {
    $page = Page::factory()->create(['name' => 'About']);
    Page::factory()->link('https://example.test/forum')->create(['name' => 'Forum']);

    pagesToNavigationMigration()->up();

    $pageItem = NavigationItem::where('label', 'About')->sole();
    $linkItem = NavigationItem::where('label', 'Forum')->sole();

    expect($pageItem->target_type)->toBe(NavigationTarget::PAGE)
        ->and($pageItem->target_value)->toBe((string) $page->id)
        ->and($linkItem->target_type)->toBe(NavigationTarget::URL)
        ->and($linkItem->target_value)->toBe('https://example.test/forum');
});

it('seeds visibility from enabled and publicity from public', function (): void {
    Page::factory()->create(['name' => 'Hidden', 'enabled' => false, 'public' => true]);
    Page::factory()->create(['name' => 'Members', 'enabled' => true, 'public' => false]);

    pagesToNavigationMigration()->up();

    expect(NavigationItem::where('label', 'Hidden')->sole()->visible)->toBeFalse()
        ->and(NavigationItem::where('label', 'Members')->sole()->public)->toBeFalse();
});

it('is idempotent, so a re-run cannot double the menu', function (): void {
    seedLegacyPages();

    pagesToNavigationMigration()->up();
    $afterFirst = migratedNavOutput(true);
    $rowsAfterFirst = NavigationItem::count();

    pagesToNavigationMigration()->up();

    // Counts every row, not just the visible ones -- a hidden item duplicating
    // would be invisible to the nav output comparison above.
    expect(migratedNavOutput(true))->toBe($afterFirst)
        ->and(NavigationItem::count())->toBe($rowsAfterFirst)
        ->and($rowsAfterFirst)->toBe(5);
});

it('leaves link rows in the pages table rather than destroying them', function (): void {
    Page::factory()->link()->create(['name' => 'Forum']);

    pagesToNavigationMigration()->up();

    expect(DB::table('pages')->where('name', 'Forum')->exists())->toBeTrue();
});
