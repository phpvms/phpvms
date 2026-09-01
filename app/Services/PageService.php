<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Path resolution and the draft/publish lifecycle for pages.
 */
class PageService extends Service
{
    /**
     * The root is claimable even though a route already answers it, because
     * `HomeController` explicitly asks this service first and only falls back to
     * the built-in home view when nothing has claimed it.
     */
    public const ROOT_PATH = '/';

    /**
     * The live page claiming a path, or null.
     *
     * Resolution happens per request and never registers routes from stored
     * data: `route:cache` snapshots routes at build time, and under Octane the
     * application boots once per worker, so a route added after boot would be
     * invisible until that worker recycled.
     */
    public function resolve(string $path): ?Page
    {
        return Page::query()
            ->live()
            ->byPath($path)
            ->first();
    }

    /**
     * Whether a page may claim this path.
     *
     * Two ways to fail: another live page already claims it, or a registered
     * route already answers it. The second check runs the router's own matcher
     * rather than a hand-rolled comparison, so `page/{slug}` and `pilots/{id}`
     * are recognised as taken without listing them here.
     */
    public function pathIsAvailable(string $path, ?Page $ignoring = null): bool
    {
        $normalised = Page::normalisePath($path);

        $claimed = Page::query()
            ->live()
            ->byPath($normalised)
            ->when($ignoring instanceof Page, fn ($q) => $q->whereKeyNot($ignoring->getKey()))
            ->exists();

        if ($claimed) {
            return false;
        }

        return !$this->pathIsReserved($normalised);
    }

    /**
     * Whether a registered route already answers this path.
     */
    public function pathIsReserved(string $path): bool
    {
        $normalised = Page::normalisePath($path);

        if ($normalised === self::ROOT_PATH) {
            return false;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($normalised, 'GET'));
        } catch (NotFoundHttpException) {
            return false;
        } catch (MethodNotAllowedHttpException) {
            // Something answers this path, just not for GET. Still taken.
            return true;
        }

        // Our own catch-all is not a claim on anything.
        return !$route->isFallback;
    }

    /**
     * HTTP methods a registered route accepts for this request's path.
     *
     * Exists because a catch-all fallback changes 405 into 404 for the whole
     * application. `RouteCollection::match()` consults fallback routes *before*
     * `checkForAlternateVerbs()`, so a GET against a POST-only route matches the
     * page fallback and never reaches the method check. Callers use this to
     * throw the 405 Laravel would have thrown on its own.
     *
     * @return list<string>
     */
    public function alternateMethodsFor(Request $request): array
    {
        $methods = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->isFallback) {
                continue;
            }

            // false === ignore the method validator, which is the whole point.
            if ($route->matches($request, false)) {
                $methods = [...$methods, ...$route->methods()];
            }
        }

        return array_values(array_unique($methods));
    }

    /**
     * Promote the draft to live content and record what was published.
     *
     * The revision is written from the draft rather than from the outgoing body
     * so that the list an author reads is a list of things that were live, and
     * restoring one puts back exactly what visitors saw.
     */
    public function publish(Page $page, ?User $by = null): Page
    {
        return DB::transaction(function () use ($page, $by): Page {
            $page->forceFill([
                'body'         => $page->draft_body,
                'status'       => PageStatus::PUBLISHED,
                'published_at' => now(),
            ])->save();

            PageRevision::create([
                'page_id'      => $page->id,
                'body'         => $page->body,
                'published_at' => $page->published_at,
                'published_by' => $by?->id,
            ]);

            return $page->refresh();
        });
    }

    /**
     * Take a page offline.
     *
     * The path claim goes with it — `live()` is what enforces uniqueness, so an
     * unpublished page holds nothing and another page may take the path. The old
     * URL 404s rather than redirecting: there is no target to redirect to, and
     * 410 would assert a permanence the author has not asserted.
     */
    public function unpublish(Page $page): Page
    {
        $page->forceFill(['status' => PageStatus::DRAFT])->save();

        return $page->refresh();
    }

    /**
     * Copy a revision back onto the draft.
     *
     * Deliberately does not publish. Restoring is how an author gets a known-good
     * version in front of them; making it live is a second, separate decision.
     */
    public function restoreRevision(Page $page, PageRevision $revision): Page
    {
        $page->forceFill(['draft_body' => $revision->body])->save();

        return $page->refresh();
    }
}
