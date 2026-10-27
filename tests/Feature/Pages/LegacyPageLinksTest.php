<?php

declare(strict_types=1);

use App\Enums\PageType;
use App\Http\Composers\PageLinksComposer;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The `seven` theme's menu, as `PageLinksComposer` hands it to the view.
 *
 * @return Collection<int, Page>
 */
function legacyPageLinks(): Collection
{
    $captured = null;

    $view = Mockery::mock(View::class);
    $view->shouldReceive('with')
        ->with('page_links', Mockery::capture($captured))
        ->andReturnSelf();

    new PageLinksComposer()->compose($view);

    return collect($captured);
}

it('leaves drafts out of the legacy menu', function (): void {
    Page::factory()->published()->create(['name' => 'Live']);
    Page::factory()->create(['name' => 'Draft']);

    expect(legacyPageLinks()->pluck('name')->all())->toBe(['Live']);
});

it('keeps link pages in the legacy menu', function (): void {
    Page::factory()->create(['name' => 'Docs', 'type' => PageType::LINK, 'link' => 'https://docs.phpvms.net']);

    expect(legacyPageLinks()->pluck('name')->all())->toBe(['Docs']);
});

it('links a page at its claimed path', function (): void {
    Page::factory()->published()->claiming('/about')->create();

    expect(legacyPageLinks()->sole()->url)->toBe(url('/about'));
});
