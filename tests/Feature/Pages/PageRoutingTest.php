<?php

declare(strict_types=1);

use App\Enums\PageType;
use App\Models\Page;
use App\Models\User;
use App\Services\PageService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * `InstalledCheck` redirects every request to the installer while the user
 * table is empty, so a site has to have someone in it before any of this is
 * reachable at all.
 */
function anInstalledSite(): User
{
    return User::factory()->create();
}

it('serves the stock home page when nothing claims the root', function (): void {
    anInstalledSite();

    $this->get('/')->assertOk()->assertViewIs('home');
});

it('serves a page that claims the root', function (): void {
    anInstalledSite();
    Page::factory()->published()->claiming('/')->create(['name' => 'Welcome Aboard']);

    $this->get('/')->assertOk()->assertViewIs('pages.index');
});

it('lets the installer redirect win over a page claiming the root', function (): void {
    Page::factory()->published()->claiming('/')->create();

    // No users at all: the site is not set up, and that outranks any content.
    $this->get('/')->assertRedirect('system/install');
});

it('falls through to the stock home page when the root page is not public', function (): void {
    anInstalledSite();
    Page::factory()->published()->private()->claiming('/')->create();

    // Throwing here would lock anonymous visitors out of the site root.
    $this->get('/')->assertOk()->assertViewIs('home');
});

it('serves the root page to a member when it is not public', function (): void {
    $user = anInstalledSite();
    Page::factory()->published()->private()->claiming('/')->create();

    $this->actingAs($user)->get('/')->assertOk()->assertViewIs('pages.index');
});

it('resolves a claimed path', function (): void {
    anInstalledSite();
    Page::factory()->published()->claiming('/about')->create();

    $this->get('/about')->assertOk()->assertViewIs('pages.index');
});

it('404s a path no page claims', function (): void {
    anInstalledSite();

    $this->get('/nothing-here')->assertNotFound();
});

it('404s a claimed path once the page is unpublished', function (): void {
    anInstalledSite();
    $page = Page::factory()->published()->claiming('/about')->create();

    $this->get('/about')->assertOk();

    app(PageService::class)->unpublish($page);

    $this->get('/about')->assertNotFound();
});

it('refuses a second page on a path another page holds, draft or published', function (): void {
    Page::factory()->claiming('/one')->create(['name' => 'First']);

    expect(fn () => Page::factory()->claiming('/one')->create(['name' => 'Second']))
        ->toThrow(UniqueConstraintViolationException::class);
});

// `HasSlug` rewrites `slug` from `name` on every save, so the slug has to be
// read back off the model rather than asserted as a literal.
it('keeps serving the legacy page/{slug} URL', function (): void {
    anInstalledSite();
    $page = Page::factory()->published()->create(['name' => 'Legacy Page']);

    expect($page->slug)->toBe('legacy-page');

    $this->get('/page/'.$page->slug)->assertOk()->assertViewIs('pages.index');
});

it('404s a disabled page by direct URL', function (): void {
    anInstalledSite();

    // The bug this change closes: `enabled` was never checked on the show route.
    $page = Page::factory()->published()->disabled()->create(['name' => 'Switched Off']);

    $this->get('/page/'.$page->slug)->assertNotFound();

    // Proves the 404 came from `enabled` and not from an unresolvable slug.
    $page->forceFill(['enabled' => true])->save();
    $this->get('/page/'.$page->slug)->assertOk();
});

it('404s an unpublished page by its legacy URL', function (): void {
    anInstalledSite();
    $page = Page::factory()->create(['name' => 'Never Published']);

    $this->get('/page/'.$page->slug)->assertNotFound();

    // Same guard: publishing it makes the very same URL resolve.
    app(PageService::class)->publish($page);
    $this->get('/page/'.$page->slug)->assertOk();
});

it('denies a guest a non-public page and allows any member', function (): void {
    anInstalledSite();
    $page = Page::factory()->published()->private()->create(['name' => 'Members Only']);

    // Unchanged behaviour: bootstrap/app.php turns a web 403 into a redirect
    // to login rather than rendering an error page.
    $this->get('/page/'.$page->slug)->assertRedirect('login');

    $this->actingAs(User::factory()->create())
        ->get('/page/'.$page->slug)
        ->assertOk();
});

it('leaves an unmatched API URL to the API stack', function (): void {
    anInstalledSite();

    $this->getJson('/api/no-such-endpoint')->assertNotFound();
});

it('lets a real route beat a page that claims the same path', function (): void {
    anInstalledSite();

    // Saving is blocked by validation, but a row that got there another way must
    // still not shadow a registered route.
    Page::factory()->published()->create([
        'path' => '/livemap',
        'body' => 'CLAIMED-BY-A-PAGE',
    ]);

    $this->get('/livemap')->assertOk()->assertDontSee('CLAIMED-BY-A-PAGE');
});

it('registers both fallbacks as serialisable controller routes', function (): void {
    // A closure route makes `route:cache` fail outright, and caching routes is
    // not optional in production.
    //
    // Asserted per route rather than by running `route:cache`: phpunit.xml:56
    // points APP_ROUTES_CACHE at a path that deliberately does not exist so
    // tests never cache routes, and routes/web.php:185 already carries a
    // pre-existing closure route (`/update`) that a whole-table assertion would
    // trip over for reasons that have nothing to do with pages.
    $fallbacks = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => $route->isFallback);

    expect($fallbacks)->toHaveCount(2);

    foreach ($fallbacks as $route) {
        expect($route->getAction('uses'))->toBeString();
    }
});

it('resolves a page added after the router booted', function (): void {
    anInstalledSite();

    // Stands in for a long-lived Octane worker: the router is already built and
    // is not rebuilt for this page. Resolution has to happen per request.
    $this->get('/late-arrival')->assertNotFound();

    Page::factory()->published()->claiming('/late-arrival')->create();

    $this->get('/late-arrival')->assertOk()->assertViewIs('pages.index');
});

it('still answers 405 for a wrong method on a real route', function (): void {
    anInstalledSite();

    // A catch-all fallback is matched before Laravel's alternate-verb check, so
    // without an explicit check the fallback swallows every method mismatch in
    // the application and turns 405 into 404. `oauth.cancel` is POST-only.
    $this->get(route('oauth.cancel'))->assertMethodNotAllowed();
});

it('reports the alternate verbs a post-only route accepts', function (): void {
    // Asserted directly rather than over HTTP: almost every path under /api is
    // also matched by an `{id}` pattern with a GET, so a GET there is answered
    // by a real route and never reaches the fallback at all. This is the input
    // the middleware acts on, for both the web and api groups.
    $methods = app(PageService::class)
        ->alternateMethodsFor(Request::create('/api/users/simbrief_username', 'DELETE'));

    expect($methods)->toContain('POST')
        ->and($methods)->not->toContain('DELETE');
});

it('reports no alternate verbs for a path nothing answers', function (): void {
    expect(app(PageService::class)->alternateMethodsFor(Request::create('/nothing-here', 'GET')))
        ->toBe([]);
});

it('404s a published page once it is switched to a link, and frees its path', function (): void {
    anInstalledSite();
    $page = Page::factory()->published()->claiming('/about')->create();

    $this->get('/about')->assertOk();

    $page->forceFill(['type' => PageType::LINK, 'link' => 'https://example.test'])->save();

    $this->get('/about')->assertNotFound();
    expect($page->refresh()->path)->toBeNull();

    $other = Page::factory()->published()->claiming('/about')->create(['name' => 'Other']);
    expect(app(PageService::class)->pathIsAvailable('/about', $other))->toBeTrue()
        ->and(app(PageService::class)->resolve('/about')->is($other))->toBeTrue();
});

it('drops a query string or fragment from a claimed path', function (): void {
    expect(Page::normalisePath('/about?ref=x'))->toBe('/about')
        ->and(Page::normalisePath('about/#team'))->toBe('/about')
        ->and(Page::normalisePath('/?utm=1'))->toBe('/')
        ->and(Page::normalisePath('?ref=x'))->toBe('/');

    anInstalledSite();
    $page = Page::factory()->published()->claiming('/about?ref=x')->create();

    $this->get('/about')->assertOk();
    expect($page->refresh()->path)->toBe('/about');
});
