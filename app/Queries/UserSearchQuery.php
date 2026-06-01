<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\UserState;
use App\Http\Requests\SearchUsersRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the Eloquent query for the public pilots list (`/users`).
 *
 * Replicates the behavior previously driven by:
 *   - prettus/l5-repository's RequestCriteria (the `?search=...` /
 *     `?search=field:value;...` syntax)
 *   - WhereCriteria for the `pilots.hide_inactive` filter
 *   - Kyslik\ColumnSortable's `?orderBy=` + `?sortedBy=` query params
 *
 * Caller (controller) decides ->paginate() vs ->get(). Default eager
 * loads match what the existing Frontend/UserController::index expected.
 */
class UserSearchQuery
{
    /**
     * Field-specific search allowlist. Maps `name:value` syntax to the
     * column being searched. Mirrors the old UserRepository::$fieldSearchable.
     *
     * @var array<string,string> column => operator ('like' or '=')
     */
    private const array FIELD_SEARCH = [
        'name'            => 'like',
        'email'           => 'like',
        'home_airport_id' => '=',
        'curr_airport_id' => '=',
        'state'           => '=',
    ];

    private function resolveOperator(string $operator): string
    {
        if ($operator !== 'like') {
            return $operator;
        }

        return $this->likeOperator();
    }

    /**
     * Free-text search columns (when search has no `field:` prefix).
     *
     * @var list<string>
     */
    private const array FREE_TEXT_COLUMNS = ['name', 'email'];

    private function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    public function build(SearchUsersRequest $request): Builder
    {
        $query = User::query()
            ->withCount(['awards'])
            ->with(['airline', 'current_airport', 'fields.field', 'home_airport', 'rank']);

        $this->applyHideInactive($query);
        $this->applyExplicitFilters($query, $request);
        $this->applySearch($query, $request);
        $this->applyOrdering($query, $request);

        return $query;
    }

    private function applyHideInactive(Builder $query): void
    {
        if (setting('pilots.hide_inactive')) {
            $query->where('state', UserState::ACTIVE);
        }
    }

    private function applyExplicitFilters(Builder $query, SearchUsersRequest $request): void
    {
        if ($request->has('state')) {
            $query->where('state', $request->input('state'));
        }

        if ($request->filled('airline_id')) {
            $query->where('airline_id', (int) $request->input('airline_id'));
        }
    }

    private function applySearch(Builder $query, SearchUsersRequest $request): void
    {
        $search = trim((string) $request->input('search', ''));
        if ($search === '') {
            return;
        }

        // Field-specific syntax: `name:John;email:foo` (OR-joined, matching
        // the legacy Prettus RequestCriteria default `searchJoin`). Falls back
        // to free-text matching when no field key is in the allowlist (so
        // payloads like `8:30` aren't silently treated as "match everything").
        if (str_contains($search, ':')) {
            $clauses = [];
            foreach (explode(';', $search) as $pair) {
                if (trim($pair) === '') {
                    continue;
                }

                [$field, $value] = array_pad(explode(':', $pair, 2), 2, '');
                $field = trim($field);
                $value = trim($value);
                if ($field === '') {
                    continue;
                }

                if ($value === '') {
                    continue;
                }

                if (!isset(self::FIELD_SEARCH[$field])) {
                    continue;
                }

                $clauses[] = [$field, $this->resolveOperator(self::FIELD_SEARCH[$field]), $value];
            }

            if ($clauses !== []) {
                $query->where(function (Builder $q) use ($clauses): void {
                    foreach ($clauses as [$field, $operator, $value]) {
                        if (in_array($operator, ['like', 'ilike'], true)) {
                            $q->orWhere($field, $operator, '%'.$value.'%');
                        } else {
                            $q->orWhere($field, '=', $value);
                        }
                    }
                });

                return;
            }

            // No allowlisted field matched — treat the whole string as free-text.
        }

        // Free-text: OR across name + email
        $like = $this->likeOperator();
        $query->where(function (Builder $q) use ($search, $like): void {
            foreach (self::FREE_TEXT_COLUMNS as $col) {
                $q->orWhere($col, $like, '%'.$search.'%');
            }
        });
    }

    private function applyOrdering(Builder $query, SearchUsersRequest $request): void
    {
        $orderBy = $request->input('orderBy');
        if (!$orderBy) {
            $query->orderBy('id', 'asc');

            return;
        }

        $direction = strtolower((string) $request->input('sortedBy', 'asc'));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $query->orderBy($orderBy, $direction);
    }
}
