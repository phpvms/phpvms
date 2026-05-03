<?php

namespace App\Queries;

use App\Http\Requests\SearchAirportsRequest;
use App\Models\Airport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Build an Eloquent\Builder for airport listing/search endpoints.
 *
 * Replaces inline Prettus RequestCriteria + WhereCriteria logic that
 * previously lived in `App\Http\Controllers\Api\AirportController::index`
 * and `::search`. Caller decides ->paginate() / ->get() / ->count().
 *
 * Backward-compatible search syntax:
 *   ''                       no filter
 *   'KJ'                     LIKE %KJ% across icao + iata + name (OR)
 *   'icao:K'                 LIKE %K% on icao only
 *   'icao:K;name:Inter'      LIKE %K% on icao OR LIKE %Inter% on name
 *   'searchJoin=and'         switch multi-field clauses to AND
 *
 * Allowed search fields and sort columns are validated by SearchAirportsRequest.
 */
class AirportSearchQueryV1
{
    public function __construct(private readonly SearchAirportsRequest $request) {}

    public function build(): Builder
    {
        $data = $this->request->validated();

        $query = Airport::query();

        $this->applyHubFilter($query, $data);

        if (!empty($data['search'])) {
            $this->applySearch($query, $data);
        }

        $this->applyOrdering($query, $data);

        return $query;
    }

    private function applyHubFilter(Builder $query, array $data): void
    {
        if (array_key_exists('hub', $data) && $data['hub'] !== '') {
            $query->where('hub', $data['hub']);

            return;
        }

        if (array_key_exists('hubs', $data) && get_truth_state($data['hubs'])) {
            $query->where('hub', true);
        }
    }

    private function applySearch(Builder $query, array $data): void
    {
        $search = $data['search'];
        $searchData = $this->parseSearchData($search);
        $searchValue = $this->parseSearchValue($search);
        $fields = $this->resolveSearchFields($data['searchFields'] ?? null, array_keys($searchData));
        $forceAnd = strtolower((string) ($data['searchJoin'] ?? 'or')) === 'and';

        $query->where(function (Builder $sub) use ($fields, $forceAnd, $searchData, $searchValue): void {
            $isFirstClause = true;

            foreach ($fields as $field => $operator) {
                $value = $searchData[$field] ?? $searchValue;
                if ($value === null || $value === '') {
                    continue;
                }

                $value = in_array($operator, ['like', 'ilike'], true)
                    ? '%'.$value.'%'
                    : $value;

                if ($isFirstClause || $forceAnd) {
                    $sub->where($field, $operator, $value);
                } else {
                    $sub->orWhere($field, $operator, $value);
                }

                $isFirstClause = false;
            }
        });
    }

    private function applyOrdering(Builder $query, array $data): void
    {
        $columns = $this->splitDelimitedValues((string) ($data['orderBy'] ?? 'icao'));
        $directions = $this->splitDelimitedValues(strtolower((string) ($data['sortedBy'] ?? 'asc')));

        foreach ($columns as $index => $column) {
            $query->orderBy($column, $directions[$index] ?? $directions[0] ?? 'asc');
        }
    }

    /**
     * @param  list<string>          $searchDataKeys
     * @return array<string, string>
     */
    private function resolveSearchFields(?string $searchFields, array $searchDataKeys): array
    {
        $defaultFields = array_fill_keys(SearchAirportsRequest::SEARCHABLE_FIELDS, 'like');
        if ($searchFields === null || $searchFields === '') {
            return $defaultFields;
        }

        $resolvedFields = [];

        foreach ($this->splitDelimitedValues($searchFields) as $part) {
            [$field, $operator] = array_pad(explode(':', $part, 2), 2, null);
            $resolvedFields[$field] = $operator !== null && $operator !== ''
                ? strtolower($operator)
                : $defaultFields[$field];
        }

        foreach ($searchDataKeys as $field) {
            $resolvedFields[$field] = $resolvedFields[$field] ?? $defaultFields[$field];
        }

        return $resolvedFields;
    }

    /**
     * @return array<string, string>
     */
    private function parseSearchData(string $search): array
    {
        if (!str_contains($search, ':')) {
            return [];
        }

        $searchData = [];

        foreach ($this->splitDelimitedValues($search) as $part) {
            [$field, $value] = array_pad(explode(':', $part, 2), 2, null);

            if ($value !== null && in_array($field, SearchAirportsRequest::SEARCHABLE_FIELDS, true)) {
                $searchData[$field] = $value;
            }
        }

        return $searchData;
    }

    private function parseSearchValue(string $search): ?string
    {
        if (!str_contains($search, ':') && !str_contains($search, ';')) {
            return $search;
        }

        foreach ($this->splitDelimitedValues($search) as $value) {
            if (!str_contains($value, ':')) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function splitDelimitedValues(string $value): array
    {
        return array_map(
            static fn (string $part): string => trim($part),
            explode(';', $value)
        );
    }
}
