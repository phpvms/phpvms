<?php

declare(strict_types=1);

namespace App\Queries;

use App\Http\Requests\SearchPirepsRequest;
use App\Models\Pirep;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the Eloquent query for PIREP list endpoints.
 *
 * Replicates the behavior previously driven by:
 *   - prettus/l5-repository's RequestCriteria (?search=field:value;... syntax)
 *   - WhereCriteria (explicit user_id / state filters via Request)
 *   - Kyslik\ColumnSortable's ?orderBy= + ?sortedBy= query params
 *
 * Mirrors Phase 4's UserSearchQuery. Caller decides ->paginate() vs ->get().
 */
class PirepSearchQuery
{
    /**
     * Field-specific search allowlist. Mirrors the old
     * PirepRepository::$fieldSearchable.
     *
     * @var list<string>
     */
    private const FIELD_SEARCH = ['user_id', 'status', 'state'];

    /**
     * Free-text search columns (when search has no `field:` prefix).
     * Pireps don't expose user-friendly free-text columns, so we OR across
     * id (UUID) and flight_number to retain something useful.
     *
     * @var list<string>
     */
    private const FREE_TEXT_COLUMNS = ['id', 'flight_number'];

    public function build(SearchPirepsRequest $request): Builder
    {
        $query = Pirep::query()
            ->with(['aircraft', 'airline', 'arr_airport', 'dpt_airport']);

        $this->applySearch($query, $request);
        $this->applyOrdering($query, $request);

        return $query;
    }

    private function applySearch(Builder $query, SearchPirepsRequest $request): void
    {
        $search = trim((string) $request->input('search', ''));
        if ($search === '') {
            return;
        }

        if (str_contains($search, ':')) {
            $clauses = [];
            foreach (explode(';', $search) as $pair) {
                if (trim($pair) === '') {
                    continue;
                }
                [$field, $value] = array_pad(explode(':', $pair, 2), 2, '');
                $field = trim($field);
                $value = trim($value);

                if ($field === '' || $value === '' || !in_array($field, self::FIELD_SEARCH, true)) {
                    continue;
                }

                $clauses[] = [$field, $value];
            }

            if ($clauses !== []) {
                $query->where(function (Builder $q) use ($clauses): void {
                    foreach ($clauses as [$field, $value]) {
                        $q->orWhere($field, '=', $value);
                    }
                });

                return;
            }
        }

        $query->where(function (Builder $q) use ($search): void {
            foreach (self::FREE_TEXT_COLUMNS as $col) {
                $q->orWhere($col, 'like', '%'.$search.'%');
            }
        });
    }

    private function applyOrdering(Builder $query, SearchPirepsRequest $request): void
    {
        $orderBy = $request->input('orderBy');
        if (!$orderBy) {
            return;
        }

        $direction = strtolower((string) $request->input('sortedBy', 'asc'));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $query->orderBy($orderBy, $direction);
    }
}
