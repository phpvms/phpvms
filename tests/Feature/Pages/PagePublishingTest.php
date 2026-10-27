<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Services\PageService;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->pages = app(PageService::class);
});

it('leaves the live page alone while the draft changes', function (): void {
    $page = Page::factory()->published()->create(['body' => 'Live copy', 'draft_body' => 'Live copy']);

    $page->forceFill(['draft_body' => 'Work in progress'])->save();

    expect($page->refresh()->body)->toBe('Live copy');
});

it('promotes the draft on publish', function (): void {
    $page = Page::factory()->published()->create(['body' => 'Live copy', 'draft_body' => 'Work in progress']);

    $this->pages->publish($page);

    expect($page->refresh()->body)->toBe('Work in progress')
        ->and($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->published_at)->not->toBeNull();
});

it('records a revision for each publish, attributed to whoever published it', function (): void {
    $author = User::factory()->create();
    $page = Page::factory()->create(['draft_body' => 'First']);

    $this->pages->publish($page, $author);

    $page->forceFill(['draft_body' => 'Second'])->save();
    $this->pages->publish($page, $author);

    expect($page->revisions()->count())->toBe(2)
        ->and($page->revisions()->pluck('body')->all())->toBe(['Second', 'First'])
        ->and($page->revisions()->first()->published_by)->toBe($author->id);
});

it('restores a revision to the draft without changing the live page', function (): void {
    $page = Page::factory()->create(['draft_body' => 'First']);
    $this->pages->publish($page);

    $page->forceFill(['draft_body' => 'Second'])->save();
    $this->pages->publish($page);

    /** @var PageRevision $first */
    $first = $page->revisions()->orderBy('id')->first();

    $this->pages->restoreRevision($page, $first);

    expect($page->refresh()->draft_body)->toBe('First')
        // Restoring stages the change; it does not ship it.
        ->and($page->body)->toBe('Second');
});

it('serves the earlier content once a restored revision is published', function (): void {
    $page = Page::factory()->create(['draft_body' => 'First']);
    $this->pages->publish($page);
    $page->forceFill(['draft_body' => 'Second'])->save();
    $this->pages->publish($page);

    $this->pages->restoreRevision($page, $page->revisions()->orderBy('id')->first());
    $this->pages->publish($page);

    expect($page->refresh()->body)->toBe('First');
});

it('keeps the overwritten version restorable after a last-write-wins collision', function (): void {
    $page = Page::factory()->create(['draft_body' => 'Author A']);
    $this->pages->publish($page);

    // Two editors, no locking: the second save simply wins.
    Page::find($page->id)->forceFill(['draft_body' => 'Author B'])->save();
    $this->pages->publish($page->refresh());

    expect($page->refresh()->body)->toBe('Author B')
        // The point of declaring last-write-wins: recovery still exists.
        ->and($page->revisions()->orderBy('id')->first()->body)->toBe('Author A');
});

it('releases the path on unpublish and lets another page take it', function (): void {
    $first = Page::factory()->published()->claiming('/about')->create(['name' => 'Old About']);

    expect($this->pages->pathIsAvailable('/about'))->toBeFalse();

    $this->pages->unpublish($first);

    expect($this->pages->pathIsAvailable('/about'))->toBeTrue()
        ->and($this->pages->resolve('/about'))->toBeNull();

    $second = Page::factory()->published()->claiming('/about')->create(['name' => 'New About']);

    expect($this->pages->resolve('/about')->is($second))->toBeTrue();
});

it('treats a path claimed by a registered route as unavailable', function (): void {
    expect($this->pages->pathIsAvailable('/livemap'))->toBeFalse()
        ->and($this->pages->pathIsAvailable('/pilots'))->toBeFalse()
        // Nothing answers this one.
        ->and($this->pages->pathIsAvailable('/marketing-landing'))->toBeTrue();
});

it('allows the root to be claimed even though a route answers it', function (): void {
    expect($this->pages->pathIsReserved('/'))->toBeFalse()
        ->and($this->pages->pathIsAvailable('/'))->toBeTrue();
});

it('lets a page keep its own path when saved again', function (): void {
    $page = Page::factory()->published()->claiming('/about')->create();

    expect($this->pages->pathIsAvailable('/about', $page))->toBeTrue()
        ->and($this->pages->pathIsAvailable('/about'))->toBeFalse();
});

it('normalises a path so one URL cannot be claimed three ways', function (): void {
    expect(Page::normalisePath('about'))->toBe('/about')
        ->and(Page::normalisePath('/about/'))->toBe('/about')
        ->and(Page::normalisePath('  /about  '))->toBe('/about')
        ->and(Page::normalisePath(''))->toBe('/')
        ->and(Page::normalisePath('/'))->toBe('/');
});

it('removes navigation items when their page is deleted', function (): void {
    $page = Page::factory()->create();
    $item = NavigationItem::factory()->forPage($page)->create();

    $page->delete();

    expect(NavigationItem::whereKey($item->id)->exists())->toBeFalse();
});

it('reports a draft status before the model has ever been reloaded', function (): void {
    // The column default only applies on insert, so without a model-level
    // default `$page->status` was null on a fresh instance.
    expect((new Page())->status)->toBe(PageStatus::DRAFT)
        ->and(Page::factory()->create()->status)->toBe(PageStatus::DRAFT);
});

it('retains a migrated page body as a revision on its first publish', function (): void {
    $page = Page::factory()->published()->create(['body' => 'Original body', 'draft_body' => 'Replacement']);

    expect($page->revisions()->count())->toBe(0);

    $this->pages->publish($page);

    $original = $page->revisions()->where('body', 'Original body')->first();

    expect($page->refresh()->body)->toBe('Replacement')
        ->and($original)->not->toBeNull()
        ->and($original->published_by)->toBeNull();

    $this->pages->restoreRevision($page, $original);

    expect($page->refresh()->draft_body)->toBe('Original body');
});

it('does not duplicate a revision that already holds the outgoing body', function (): void {
    $page = Page::factory()->create(['draft_body' => 'First']);
    $this->pages->publish($page);
    $page->forceFill(['draft_body' => 'Second'])->save();
    $this->pages->publish($page);

    expect($page->revisions()->count())->toBe(2);
});

it('refuses to publish onto a path another live page claims', function (): void {
    Page::factory()->published()->claiming('/about')->create();
    $page = Page::factory()->claiming('/about')->create(['draft_body' => 'Mine']);

    expect(fn () => $this->pages->publish($page))->toThrow(ValidationException::class);

    expect($page->refresh()->status)->toBe(PageStatus::DRAFT)
        ->and($page->body)->toBeNull()
        ->and($page->revisions()->count())->toBe(0);
});
