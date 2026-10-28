<?php

declare(strict_types=1);

use App\Enums\NavigationTarget;
use App\Enums\PageType;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

it('creates a linked menu item when the page is added to the menu', function (): void {
    NavigationItem::factory()->create(['sort' => 4]);

    Livewire::test(CreatePage::class)
        ->fillForm([
            'name'        => 'Rules',
            'type'        => PageType::PAGE->value,
            'public'      => false,
            'enabled'     => true,
            'draft_body'  => 'Be nice',
            'add_to_menu' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::where('name', 'Rules')->firstOrFail();
    $item = NavigationItem::where('target_value', (string) $page->id)->sole();

    expect($item->target_type)->toBe(NavigationTarget::PAGE)
        ->and($item->name)->toBe('Rules')
        ->and($item->label)->toBe('Rules')
        ->and($item->public)->toBeFalse()
        ->and($item->visible)->toBeTrue()
        ->and($item->parent_id)->toBeNull()
        ->and($item->sort)->toBe(5);
});

it('uses the given menu label', function (): void {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'name'        => 'Rules',
            'type'        => PageType::PAGE->value,
            'draft_body'  => 'Be nice',
            'add_to_menu' => true,
            'menu_label'  => 'House rules',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(NavigationItem::sole()->label)->toBe('House rules');
});

it('creates no menu item when the toggle is off', function (): void {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'name'        => 'Rules',
            'type'        => PageType::PAGE->value,
            'draft_body'  => 'Be nice',
            'add_to_menu' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Page::where('name', 'Rules')->exists())->toBeTrue()
        ->and(NavigationItem::count())->toBe(0);
});

it('does not offer the menu fields on the edit form', function (): void {
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertFormFieldDoesNotExist('add_to_menu')
        ->assertFormFieldDoesNotExist('menu_label');
});

it('does not add a menu item for an editor without the menu permission', function (): void {
    $editor = User::factory()->create();
    $editor->givePermissionTo(['view:dashboard', 'view:page', 'edit:page']);
    $this->actingAs($editor);

    Livewire::test(CreatePage::class)
        ->assertFormFieldHidden('add_to_menu')
        ->fillForm([
            'name'        => 'Rules',
            'type'        => PageType::PAGE->value,
            'public'      => false,
            'enabled'     => true,
            'draft_body'  => 'Be nice',
            'add_to_menu' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Page::where('name', 'Rules')->exists())->toBeTrue()
        ->and(NavigationItem::count())->toBe(0);
});
