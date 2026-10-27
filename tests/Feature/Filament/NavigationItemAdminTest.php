<?php

declare(strict_types=1);

use App\Enums\NavigationTarget;
use App\Filament\Resources\NavigationItems\Pages\CreateNavigationItem;
use App\Filament\Resources\NavigationItems\Pages\EditNavigationItem;
use App\Filament\Resources\NavigationItems\Pages\ListNavigationItems;
use App\Filament\Resources\NavigationItems\Schemas\NavigationItemForm;
use App\Models\NavigationItem;
use App\Models\Page;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();
});

it('creates an item pointing at a page', function (): void {
    $page = Page::factory()->create();

    Livewire::test(CreateNavigationItem::class)
        ->fillForm([
            'name'         => 'Rules',
            'label'        => 'Rules',
            'target_type'  => NavigationTarget::PAGE->value,
            'target_value' => $page->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = NavigationItem::where('name', 'Rules')->sole();

    expect($item->target_type)->toBe(NavigationTarget::PAGE)
        ->and($item->target_value)->toBe((string) $page->id)
        ->and($item->visible)->toBeTrue();
});

it('requires a target for the chosen type', function (): void {
    Livewire::test(CreateNavigationItem::class)
        ->fillForm([
            'name'        => 'Docs',
            'label'       => 'Docs',
            'target_type' => NavigationTarget::URL->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['target_value' => 'required']);
});

it('edits an item and nests it under another', function (): void {
    $parent = NavigationItem::factory()->create();
    $item = NavigationItem::factory()->create(['label' => 'Old']);

    Livewire::test(EditNavigationItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['label' => 'New', 'parent_id' => $parent->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->refresh()->label)->toBe('New')
        ->and($item->parent_id)->toBe($parent->id);
});

it('hides an item with the visible toggle in the table', function (): void {
    $item = NavigationItem::factory()->create();

    Livewire::test(ListNavigationItems::class)
        ->call('updateTableColumnState', 'visible', $item->getKey(), false);

    expect($item->refresh()->visible)->toBeFalse()
        ->and(NavigationItem::visibleTo(true)->count())->toBe(0);
});

it('lays the menu out as a tree, children under their parent', function (): void {
    $a = NavigationItem::factory()->create(['name' => 'A', 'sort' => 1]);
    $b = NavigationItem::factory()->create(['name' => 'B', 'sort' => 2]);
    $a2 = NavigationItem::factory()->create(['name' => 'A2', 'sort' => 4, 'parent_id' => $a->id]);
    $a1 = NavigationItem::factory()->create(['name' => 'A1', 'sort' => 3, 'parent_id' => $a->id]);
    $a1x = NavigationItem::factory()->create(['name' => 'A1x', 'sort' => 9, 'parent_id' => $a1->id]);

    Livewire::test(ListNavigationItems::class)
        ->assertCanSeeTableRecords([$a, $a1, $a1x, $a2, $b], inOrder: true)
        ->assertTableColumnFormattedStateSet('name', 'A', $a)
        ->assertTableColumnFormattedStateSet('name', '— A1', $a1)
        ->assertTableColumnFormattedStateSet('name', '— — A1x', $a1x);
});

it('reorders items by dragging and keeps children with their parent', function (): void {
    $a = NavigationItem::factory()->create(['sort' => 1]);
    $a1 = NavigationItem::factory()->create(['sort' => 2, 'parent_id' => $a->id]);
    $b = NavigationItem::factory()->create(['sort' => 3]);

    Livewire::test(ListNavigationItems::class)
        ->call('toggleTableReordering')
        ->assertCanSeeTableRecords([$a, $a1, $b], inOrder: true)
        ->call('reorderTable', [$b->id, $a->id, $a1->id]);

    expect($b->refresh()->sort)->toBeLessThan($a->refresh()->sort)
        ->and($a1->refresh()->sort)->toBeGreaterThan($a->sort);

    Livewire::test(ListNavigationItems::class)
        ->assertCanSeeTableRecords([$b, $a, $a1], inOrder: true);
});

it('refuses to delete an item that has children', function (): void {
    $parent = NavigationItem::factory()->create();
    $child = NavigationItem::factory()->create(['parent_id' => $parent->id]);

    expect($parent->delete())->toBeFalse()
        ->and(NavigationItem::find($parent->id))->not->toBeNull()
        ->and(NavigationItem::find($child->id))->not->toBeNull();
});

it('deletes a leaf item', function (): void {
    $leaf = NavigationItem::factory()->create();

    expect($leaf->delete())->toBeTrue()
        ->and(NavigationItem::find($leaf->id))->toBeNull();
});

it('hides the row delete action for an item with children', function (): void {
    $parent = NavigationItem::factory()->create();
    NavigationItem::factory()->create(['parent_id' => $parent->id]);
    $leaf = NavigationItem::factory()->create();

    Livewire::test(ListNavigationItems::class)
        ->assertActionHidden(TestAction::make('delete')->table($parent))
        ->assertActionVisible(TestAction::make('delete')->table($leaf));
});

it('hides the header delete action on the edit page for an item with children', function (): void {
    $parent = NavigationItem::factory()->create();
    NavigationItem::factory()->create(['parent_id' => $parent->id]);

    Livewire::test(EditNavigationItem::class, ['record' => $parent->id])
        ->assertActionHidden('delete');
});

it('bulk delete leaves a parent and its children and removes leaves', function (): void {
    $parent = NavigationItem::factory()->create();
    $child = NavigationItem::factory()->create(['parent_id' => $parent->id]);
    $leaf = NavigationItem::factory()->create();

    Livewire::test(ListNavigationItems::class)
        ->selectTableRecords([$parent->id, $leaf->id])
        ->callAction(TestAction::make('delete')->table()->bulk());

    expect(NavigationItem::find($parent->id))->not->toBeNull()
        ->and(NavigationItem::find($child->id))->not->toBeNull()
        ->and(NavigationItem::find($leaf->id))->toBeNull();
});

it('offers only GET routes as menu targets', function (): void {
    $options = new ReflectionMethod(NavigationItemForm::class, 'routeOptions')->invoke(null);

    // A menu link is a GET, so a POST-only route would open the wrong handler.
    expect($options)->toHaveKey('frontend.dashboard.index')
        ->not->toHaveKey('frontend.profile.tokens.store');
});
