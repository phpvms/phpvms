<?php

declare(strict_types=1);

use App\Enums\NavigationTarget;
use App\Models\NavigationItem;

function seedSpaNavigationItems(): void
{
    (require base_path('database/migrations_data/2026_10_27_000000_seed_spa_navigation_items.php'))->up();
}

beforeEach(function (): void {
    NavigationItem::query()->delete();
});

it('seeds the built-in skylight links ahead of existing items, for members only', function (): void {
    NavigationItem::factory()->create(['label' => 'Docs', 'sort' => 0]);

    seedSpaNavigationItems();

    $items = NavigationItem::query()->orderBy('sort')->get();

    expect($items->pluck('target_value')->all())->toBe([
        'frontend.dashboard.index',
        'frontend.flights.index',
        'frontend.flights.bids',
        'frontend.tours.index',
        'frontend.livemap.index',
        'frontend.pireps.index',
        'frontend.profile.index',
        $items->last()->target_value,
    ])
        ->and($items->last()->label)->toBe('Docs')
        ->and($items->take(7)->every(fn (NavigationItem $item): bool => $item->target_type === NavigationTarget::ROUTE && !$item->public))->toBeTrue()
        ->and($items->first()->label)->toBe('common.dashboard')
        ->and($items->take(7)->pluck('name')->all())->toBe(['Dashboard', 'Flights', 'My Bids', 'Tours', 'Live Map', 'Logbook', 'Profile']);
});

it('does not double the menu when run again', function (): void {
    seedSpaNavigationItems();
    seedSpaNavigationItems();

    expect(NavigationItem::query()->count())->toBe(7);
});

it('skips a built-in an admin already added', function (): void {
    NavigationItem::factory()->forRoute('frontend.tours.index')->create(['label' => 'Our tours']);

    seedSpaNavigationItems();

    expect(NavigationItem::query()->where('target_value', 'frontend.tours.index')->count())->toBe(1)
        ->and(NavigationItem::query()->count())->toBe(7);
});
