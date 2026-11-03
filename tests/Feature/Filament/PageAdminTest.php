<?php

declare(strict_types=1);

use App\Enums\NavigationTarget;
use App\Enums\PageStatus;
use App\Filament\RelationManagers\PageRevisionsRelationManager;
use App\Filament\Resources\NavigationItems\Pages\ListNavigationItems;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Services\PageService;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    createAdminUser();
});

it('saves edits to the draft and leaves the live page alone', function (): void {
    $page = Page::factory()->published()->create([
        'body'       => 'Live copy',
        'draft_body' => 'Live copy',
    ]);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['draft_body' => 'Work in progress'])
        ->call('save')
        ->assertHasNoFormErrors();

    // RichEditor stores markup, so the draft comes back wrapped. What matters
    // is that it changed and that `body` -- what visitors see -- did not.
    expect($page->refresh()->draft_body)->toContain('Work in progress')
        ->and($page->body)->toBe('Live copy');
});

it('publishes the draft from the edit page', function (): void {
    $page = Page::factory()->create(['draft_body' => 'Ready to go']);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->callAction('publish')
        ->assertNotified();

    expect($page->refresh()->body)->toBe('Ready to go')
        ->and($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->revisions()->count())->toBe(1);
});

it('unpublishes a live page from the edit page', function (): void {
    $page = Page::factory()->published()->claiming('/about')->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->callAction('unpublish')
        ->assertNotified();

    expect($page->refresh()->status)->toBe(PageStatus::DRAFT);
});

it('rejects a path that a registered route already answers', function (): void {
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['path' => '/livemap'])
        ->call('save')
        ->assertHasFormErrors(['path']);
});

it('rejects a path another published page already claims', function (): void {
    Page::factory()->published()->claiming('/about')->create();
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['path' => '/about'])
        ->call('save')
        ->assertHasFormErrors(['path']);
});

it('accepts a free path and normalises it', function (): void {
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['path' => 'marketing/landing/'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->path)->toBe('/marketing/landing');
});

it('lets a page keep the path it already holds', function (): void {
    $page = Page::factory()->published()->claiming('/about')->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['path' => '/about', 'name' => 'About Us Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->path)->toBe('/about');
});

it('lists navigation items in menu order', function (): void {
    $second = NavigationItem::factory()->create(['label' => 'Second', 'sort' => 2]);
    $first = NavigationItem::factory()->create(['label' => 'First', 'sort' => 1]);

    Livewire::test(ListNavigationItems::class)
        ->assertCanSeeTableRecords([$first, $second], inOrder: true);
});

it('hides an item from the menu without touching its page', function (): void {
    $page = Page::factory()->published()->claiming('/about')->create();
    $item = NavigationItem::factory()->forPage($page)->create();

    // Hiding is what the table's toggle column writes. The behaviour that
    // matters is downstream of it: the menu drops the item, the page does not.
    $item->forceFill(['visible' => false])->save();

    expect(NavigationItem::visibleTo(true)->count())->toBe(0)
        // The whole point of the split: the page is still there.
        ->and(app(PageService::class)->resolve('/about')->is($page))->toBeTrue();
});

it('resolves a page-targeted item to that page url', function (): void {
    $page = Page::factory()->published()->claiming('/about')->create();
    $item = NavigationItem::factory()->forPage($page)->create();

    expect($item->target_type)->toBe(NavigationTarget::PAGE)
        ->and($item->resolveUrl())->toBe(url('/about'));
});

it('shows a danger notification and publishes nothing when a route now owns the path', function (): void {
    $page = Page::factory()->claiming('/livemap')->create(['draft_body' => 'Mine']);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->callAction('publish')
        ->assertNotified();

    expect($page->refresh()->status)->toBe(PageStatus::DRAFT)
        ->and($page->body)->toBeNull()
        ->and($page->revisions()->count())->toBe(0);
});

it('rejects a path another draft already holds', function (): void {
    Page::factory()->claiming('/about')->create();
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['path' => '/about'])
        ->call('save')
        ->assertHasFormErrors(['path']);
});

it('offers a preview action that opens the preview route', function (): void {
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertActionHasUrl('preview', route('frontend.pages.preview', ['page' => $page->getKey()]))
        ->assertActionShouldOpenUrlInNewTab('preview');
});

it('restores a revision to the draft through the relation manager', function (): void {
    $this->actingAs(createAdminUser());
    $page = Page::factory()->create(['draft_body' => 'First']);
    app(PageService::class)->publish($page);
    $page->forceFill(['draft_body' => 'Second'])->save();
    app(PageService::class)->publish($page);

    $first = $page->revisions()->where('body', 'First')->first();

    Livewire::test(PageRevisionsRelationManager::class, [
        'ownerRecord' => $page,
        'pageClass'   => EditPage::class,
    ])
        ->assertCanSeeTableRecords($page->revisions)
        ->callAction(TestAction::make('restore')->table($first))
        ->assertNotified();

    expect($page->refresh()->draft_body)->toBe('First')
        ->and($page->body)->toBe('Second');
});

it('reloads the edit form draft after a restore, so saving keeps it', function (): void {
    // HTML bodies, as the rich editor stores them: from Filament 5.11 it reports
    // a plain-text body wrapped in a paragraph.
    $this->actingAs(createAdminUser());
    $page = Page::factory()->create(['draft_body' => '<p>First</p>']);
    app(PageService::class)->publish($page);
    $page->forceFill(['draft_body' => '<p>Second</p>'])->save();
    app(PageService::class)->publish($page);

    $edit = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertSchemaStateSet(['draft_body' => '<p>Second</p>']);

    // What the relation manager does on the same page.
    app(PageService::class)->restoreRevision($page, $page->revisions()->where('body', '<p>First</p>')->firstOrFail());
    $edit->dispatch(PageRevisionsRelationManager::RESTORED_EVENT);

    $edit->call('save')->assertHasNoFormErrors();

    expect($page->refresh()->draft_body)->toBe('<p>First</p>');
});

it('dispatches the restore event from the relation manager', function (): void {
    $this->actingAs(createAdminUser());
    $page = Page::factory()->create(['draft_body' => 'First']);
    app(PageService::class)->publish($page);

    Livewire::test(PageRevisionsRelationManager::class, [
        'ownerRecord' => $page,
        'pageClass'   => EditPage::class,
    ])
        ->callAction(TestAction::make('restore')->table($page->revisions()->firstOrFail()))
        ->assertDispatched(PageRevisionsRelationManager::RESTORED_EVENT);
});
