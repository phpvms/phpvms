<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Answers any unmatched `/api/...` URL.
 *
 * Exists only to stop the global page fallback in routes/web.php from claiming
 * the API prefix and answering misses out of the web middleware group. Throws
 * exactly what an unmatched request threw before either fallback existed.
 *
 * A controller rather than a closure because `route:cache` cannot serialise a
 * closure, and caching routes is not optional in production.
 */
class FallbackController extends Controller
{
    public function __invoke(): never
    {
        throw new NotFoundHttpException();
    }
}
