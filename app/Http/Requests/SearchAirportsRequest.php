<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use Closure;

/**
 * Validates query string for /api/airports and /api/airports/search.
 *
 * Backward compatible with the legacy Prettus RequestCriteria syntax:
 *   ?search=KJ                        free-text across icao/iata/name
 *   ?search=icao:KJ                   field-specific (LIKE %KJ%)
 *   ?search=icao:K;name:Inter         multi-field (OR by default; AND with searchJoin=and)
 *
 * Fields:
 *   search    Free-text or `field:value[;field:value...]` (LIKE-style)
 *   searchFields Restrict free-text search fields (`icao`, `iata`, `name`; optional `:like` / `:=`)
 *   searchJoin   `or` (default) or `and` for multi-field search clauses
 *   hub       Legacy /api/airports filter (presence-based, preserves `?hub=0`)
 *   hubs      Truthy => filter to hubs only (legacy /api/airports/search name)
 *   orderBy   Allowlisted sortable column(s); semicolon-delimited for multi-sort
 *   sortedBy  asc | desc list; semicolon-delimited to match orderBy
 */
class SearchAirportsRequest extends FormRequest
{
    public const array SEARCHABLE_FIELDS = ['iata', 'icao', 'name'];

    public const array SEARCHABLE_OPERATORS = ['=', 'like'];

    public const array SEARCH_MODES = ['substring', 'prefix'];

    public const array ORDERABLE_FIELDS = [
        'id',
        'iata',
        'icao',
        'name',
        'hub',
        'notes',
        'elevation',
        'location',
        'region',
        'country',
    ];

    public const array SORT_DIRECTIONS = ['asc', 'desc'];

    #[\Override]
    public function rules(): array
    {
        $maxLimit = (int) config('phpvms.pagination.max', 100);

        return [
            'search'       => ['sometimes', 'string', 'max:255'],
            'limit'        => ['sometimes', 'integer', 'min:1', 'max:'.$maxLimit],
            'searchFields' => [
                'sometimes',
                'string',
                'max:255',
                fn (string $attribute, mixed $value, Closure $fail) => $this->validateSearchFields($attribute, $value, $fail),
            ],
            'searchJoin' => [
                'sometimes',
                'string',
                fn (string $attribute, mixed $value, Closure $fail) => $this->validateSearchJoin($attribute, $value, $fail),
            ],
            // Controls how `like` operator values are wrapped:
            //   'substring' (default, backward compat) -> "%value%"
            //   'prefix'                               -> "value%"
            // Exact `=` operators ignore this. RouteForge passes 'prefix' so
            // its airport typeahead matches starts-with only.
            'searchMode' => [
                'sometimes',
                'string',
                fn (string $attribute, mixed $value, Closure $fail) => $this->validateDelimitedValues($attribute, $value, $fail, self::SEARCH_MODES, lowercase: true),
            ],
            // hub/hubs intentionally permissive: get_truth_state() in the
            // Query class preserves the legacy endpoint behavior.
            'hub'     => ['sometimes'],
            'hubs'    => ['sometimes'],
            'orderBy' => [
                'sometimes',
                'string',
                'max:255',
                fn (string $attribute, mixed $value, Closure $fail) => $this->validateDelimitedValues($attribute, $value, $fail, self::ORDERABLE_FIELDS),
            ],
            'sortedBy' => [
                'sometimes',
                'string',
                'max:255',
                fn (string $attribute, mixed $value, Closure $fail) => $this->validateDelimitedValues($attribute, $value, $fail, self::SORT_DIRECTIONS, lowercase: true),
            ],
        ];
    }

    private function validateSearchFields(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($this->splitDelimitedValues((string) $value) as $part) {
            [$field, $operator] = array_pad(explode(':', $part, 2), 2, null);

            if (!in_array($field, self::SEARCHABLE_FIELDS, true)) {
                $fail(sprintf('The %s field is invalid.', $attribute));

                return;
            }

            if ($operator === '') {
                $fail(sprintf('The %s field is invalid.', $attribute));

                return;
            }

            if ($operator !== null && !in_array(strtolower($operator), self::SEARCHABLE_OPERATORS, true)) {
                $fail(sprintf('The %s field is invalid.', $attribute));

                return;
            }
        }
    }

    private function validateSearchJoin(string $attribute, mixed $value, Closure $fail): void
    {
        if (!in_array(strtolower((string) $value), ['and', 'or'], true)) {
            $fail(sprintf('The %s field is invalid.', $attribute));
        }
    }

    private function validateDelimitedValues(
        string $attribute,
        mixed $value,
        Closure $fail,
        array $allowed,
        bool $lowercase = false,
    ): void {
        foreach ($this->splitDelimitedValues((string) $value) as $part) {
            $candidate = $lowercase ? strtolower($part) : $part;

            if (!in_array($candidate, $allowed, true)) {
                $fail(sprintf('The %s field is invalid.', $attribute));

                return;
            }
        }
    }

    /**
     * Split semicolon-delimited legacy query params.
     *
     * @return list<string>
     */
    private function splitDelimitedValues(string $value): array
    {
        return array_map(
            trim(...),
            explode(';', $value)
        );
    }
}
