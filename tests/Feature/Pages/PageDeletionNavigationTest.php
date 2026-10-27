<?php

declare(strict_types=1);

use App\Models\NavigationItem;
use App\Models\Page;
use App\Services\NavigationService;

it('removes a leaf item when its page is deleted', function (): void {
    $page = Page::factory()->create();
    $item = NavigationItem::factory()->forPage($page)->create();

    $page->delete();

    expect(NavigationItem::find($item->id))->toBeNull();
});

it('keeps an item with children as a group heading when its page is deleted', function (): void {
    $page = Page::factory()->create();
    $parent = NavigationItem::factory()->forPage($page)->create(['label' => 'Group']);
    $child = NavigationItem::factory()->create(['label' => 'Child', 'parent_id' => $parent->id]);

    $page->delete();

    expect(NavigationItem::find($parent->id))->not->toBeNull()
        ->and(NavigationItem::find($child->id))->not->toBeNull();

    $menu = app(NavigationService::class)->menu(true);

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('Group')
        ->and($menu[0]['children'])->toHaveCount(1)
        ->and($menu[0]['children'][0]['label'])->toBe('Child');
});
