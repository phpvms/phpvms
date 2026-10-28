<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
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
     * The route name of the page fallback in routes/web.php.
     */
    public const PAGE_FALLBACK_ROUTE = 'frontend.pages.show.path';

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
     * Two ways to fail: another page already holds it (published or draft; a
     * path is held by at most one page, and the database enforces it), or a
     * registered route already answers it. The second check runs the router's own matcher
     * rather than a hand-rolled comparison, so `page/{slug}` and `pilots/{id}`
     * are recognised as taken without listing them here.
     */
    public function pathIsAvailable(string $path, ?Page $ignoring = null): bool
    {
        $normalised = Page::normalisePath($path);

        $claimed = Page::query()
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

        // The page fallback is not a claim on anything. Any other route is,
        // including the API fallback, which owns everything under `/api`.
        if ($route->getName() !== self::PAGE_FALLBACK_ROUTE) {
            return true;
        }

        // Fallbacks are matched before the method check, so a path that only a
        // POST route answers lands here too. Visitors would get a 405 there.
        return $this->alternateMethodsFor(Request::create($normalised, 'GET')) !== [];
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
     *
     * Content that went live before revisions existed (a migrated page) has no
     * revision of its own, so the outgoing body is retained first; otherwise the
     * first publish after upgrade would destroy it.
     *
     * @throws ValidationException When another page holds the path or a route answers it.
     */
    public function publish(Page $page, ?User $by = null): Page
    {
        return DB::transaction(function () use ($page, $by): Page {
            $this->assertPathUnclaimed($page);
            $this->retainOutgoingContent($page);

            try {
                $page->forceFill([
                    'body'         => $page->draft_body,
                    'status'       => PageStatus::PUBLISHED,
                    'published_at' => now(),
                ])->save();
            } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
                throw $this->toPathValidationException($uniqueConstraintViolationException);
            }

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
     * The last check before a page goes live on a path: another page may have
     * been saved onto it, or a route may have been registered over it, since the
     * author chose it. Uses the same route check as the form.
     *
     * @throws ValidationException
     */
    private function assertPathUnclaimed(Page $page): void
    {
        if (!filled($page->path)) {
            return;
        }

        if ($this->pathIsReserved($page->path)) {
            throw ValidationException::withMessages([
                'path' => __('filament.system_pages.path_taken_route'),
            ]);
        }

        $claimed = Page::query()
            ->byPath($page->path)
            ->whereKeyNot($page->getKey())
            ->exists();

        if ($claimed) {
            throw ValidationException::withMessages([
                'path' => __('filament.system_pages.path_claimed'),
            ]);
        }
    }

    /**
     * Whether a unique-constraint failure was on the path column, so callers
     * can turn it into the same validation error the up-front check gives.
     */
    public function isPathViolation(UniqueConstraintViolationException $exception): bool
    {
        return str_contains($exception->getMessage(), 'path');
    }

    /**
     * The validation error for losing a race to claim a path.
     *
     * @param string $key The error key; Livewire forms prefix their state with `data.`.
     */
    public function toPathValidationException(UniqueConstraintViolationException $exception, string $key = 'path'): ValidationException|UniqueConstraintViolationException
    {
        if (!$this->isPathViolation($exception)) {
            return $exception;
        }

        return ValidationException::withMessages([
            $key => __('filament.system_pages.path_claimed'),
        ]);
    }

    /**
     * Write the currently published body as a revision unless the latest
     * revision already holds it.
     */
    private function retainOutgoingContent(Page $page): void
    {
        if ($page->status !== PageStatus::PUBLISHED || !filled($page->body)) {
            return;
        }

        if ($page->revisions()->first()?->body === $page->body) {
            return;
        }

        PageRevision::create([
            'page_id'      => $page->id,
            'body'         => $page->body,
            'published_at' => $page->published_at,
            'published_by' => null,
        ]);
    }

    /**
     * Take a page offline.
     *
     * The page keeps its path -- a path is held by at most one page, published or
     * not -- but stops answering it. The old
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
