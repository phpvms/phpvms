<?php

namespace App\Repositories\Criteria;

use Illuminate\Http\Request;
use Prettus\Repository\Criteria\RequestCriteria as BaseRequestCriteria;

/**
 * Request-driven search/sort criteria that only honours the parts of the
 * l5-repository query grammar that are safe for a caller to control.
 *
 * The upstream criteria lets any request pick the selected columns
 * (`filter`), eager-load any relation (`with`, `withCount`) and left-join any
 * table through `orderBy=table:col|col`. On a public endpoint that is enough
 * to read `users.*` (api keys, password hashes) or `users.oauth_tokens`
 * through an Airport. Only `search`, `searchFields`, `searchJoin` and a plain
 * single-table `orderBy`/`sortedBy` are passed through.
 */
class RequestCriteria extends BaseRequestCriteria
{
    private const DROPPED_PARAMS = ['filter', 'with', 'withCount'];

    private const SAFE_COLUMN = '/^[A-Za-z0-9_]+$/';

    private const SAFE_DIRECTION = '/^(asc|desc)$/i';

    public function __construct(Request $request)
    {
        parent::__construct(self::sanitize($request));
    }

    /**
     * Return a copy of the request with the unsafe criteria parameters removed
     */
    private static function sanitize(Request $request): Request
    {
        $params = config('repository.criteria.params', []);
        $request = clone $request;

        foreach (self::DROPPED_PARAMS as $param) {
            self::forget($request, $params[$param] ?? $param);
        }

        $orderByKey = $params['orderBy'] ?? 'orderBy';
        $sortedByKey = $params['sortedBy'] ?? 'sortedBy';

        $orderBy = self::filterList($request->get($orderByKey), self::SAFE_COLUMN);
        $sortedBy = self::filterList($request->get($sortedByKey), self::SAFE_DIRECTION);

        self::forget($request, $orderByKey);
        self::forget($request, $sortedByKey);

        if ($orderBy !== null) {
            $request->query->set($orderByKey, $orderBy);
            $request->query->set($sortedByKey, $sortedBy ?? 'asc');
        }

        return $request;
    }

    /**
     * Keep only the `;`-separated entries that match the pattern
     */
    private static function filterList(mixed $value, string $pattern): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $kept = array_values(array_filter(
            array_map('trim', explode(';', $value)),
            fn (string $item): bool => preg_match($pattern, $item) === 1,
        ));

        return $kept === [] ? null : implode(';', $kept);
    }

    private static function forget(Request $request, string $key): void
    {
        $request->query->remove($key);
        $request->request->remove($key);
        $request->attributes->remove($key);
    }
}
