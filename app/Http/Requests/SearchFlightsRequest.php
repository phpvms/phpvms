<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use Closure;

/**
 * Validates query string for the Flight list/search endpoints:
 *   - /api/flights, /api/flights/search   (Api/FlightController::index/search)
 *   - /flights                            (Frontend/FlightController::index/search)
 *
 * Backward compatible with the legacy Prettus searchCriteria filters and
 * RequestCriteria's ?orderBy=&sortedBy= pair. Field/value/relation filtering
 * lives in FlightSearchQuery; this request only validates and shapes input.
 *
 * Filter fields:
 *   flight_id        Exact match on flights.id
 *   airline_id       Filter by airline
 *   flight_number    Exact match (was 'like' under Prettus, but every caller
 *                    used exact matching against the searchCriteria branch)
 *   callsign         Exact match
 *   flight_type      PIREP flight_type code; '0' is treated as no filter
 *   route_code       Exact match
 *   dpt_airport_id   ICAO id (uppercased before match)
 *   dep_icao         Alias for dpt_airport_id
 *   arr_airport_id   ICAO id (uppercased before match)
 *   arr_icao         Alias for arr_airport_id
 *   dgt              distance >= dgt (kilometres / miles per setting)
 *   dlt              distance <= dlt
 *   tgt              flight_time >= tgt (minutes)
 *   tlt              flight_time <= tlt
 *   subfleet_id      Filter via subfleets relation
 *   type_rating_id   Filter via Typerating -> subfleets relation
 *   icao_type        Filter via Aircraft -> subfleets relation
 *
 * Pagination + sort:
 *   search    Free-text or `field:value[;field:value...]` (max 255 chars)
 *   orderBy   One of ORDERABLE_FIELDS
 *   sortedBy  asc | desc
 *   page      Pagination page number (min 1)
 *   limit     Page size (min 1)
 */
class SearchFlightsRequest extends FormRequest
{
    public const array ORDERABLE_FIELDS = [
        'id',
        'airline_id',
        'flight_number',
        'callsign',
        'route_code',
        'route_leg',
        'dpt_airport_id',
        'dpt_time',
        'arr_airport_id',
        'arr_time',
        'distance',
        'flight_time',
        'flight_type',
        'created_at',
        'updated_at',
    ];

    public const array SORT_DIRECTIONS = ['asc', 'desc'];

    /**
     * Alias kyslik/column-sortable's ?sort=&direction= params (emitted by
     *
     * @sortablelink in blade) onto our canonical ?orderBy=&sortedBy= params
     * before validation runs. Either pair works; explicit orderBy wins.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if (!$this->filled('orderBy') && $this->filled('sort')) {
            $merge['orderBy'] = $this->input('sort');
        }
        if (!$this->filled('sortedBy') && $this->filled('direction')) {
            $merge['sortedBy'] = $this->input('direction');
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'search'         => ['sometimes', 'string', 'max:255'],
            'flight_id'      => ['sometimes', 'string'],
            'airline_id'     => ['sometimes', 'integer'],
            'flight_number'  => ['sometimes', 'string', 'max:32'],
            'callsign'       => ['sometimes', 'string', 'max:32'],
            'flight_type'    => ['sometimes', 'string', 'max:8'],
            'route_code'     => ['sometimes', 'string', 'max:8'],
            'dpt_airport_id' => ['sometimes', 'string', 'max:8'],
            'dep_icao'       => ['sometimes', 'string', 'max:8'],
            'arr_airport_id' => ['sometimes', 'string', 'max:8'],
            'arr_icao'       => ['sometimes', 'string', 'max:8'],
            'dgt'            => ['sometimes', 'integer', 'min:0'],
            'dlt'            => ['sometimes', 'integer', 'min:0'],
            'tgt'            => ['sometimes', 'integer', 'min:0'],
            'tlt'            => ['sometimes', 'integer', 'min:0'],
            'subfleet_id'    => ['sometimes', 'integer'],
            'type_rating_id' => ['sometimes', 'integer'],
            'icao_type'      => ['sometimes', 'string', 'max:8'],
            'orderBy'        => [
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
            'page'  => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max'         => 'The search field must not exceed 255 characters.',
            'flight_number.max'  => 'The flight number must not exceed 32 characters.',
            'callsign.max'       => 'The callsign must not exceed 32 characters.',
            'flight_type.max'    => 'The flight type must not exceed 8 characters.',
            'route_code.max'     => 'The route code must not exceed 8 characters.',
            'dpt_airport_id.max' => 'The departure airport ID must not exceed 8 characters.',
            'dep_icao.max'       => 'The departure ICAO must not exceed 8 characters.',
            'arr_airport_id.max' => 'The arrival airport ID must not exceed 8 characters.',
            'arr_icao.max'       => 'The arrival ICAO must not exceed 8 characters.',
            'icao_type.max'      => 'The ICAO type must not exceed 8 characters.',
            'dgt.min'            => 'The minimum distance must be 0 or greater.',
            'dlt.min'            => 'The maximum distance must be 0 or greater.',
            'tgt.min'            => 'The minimum flight time must be 0 or greater.',
            'tlt.min'            => 'The maximum flight time must be 0 or greater.',
            'orderBy.max'        => 'The orderBy field must not exceed 255 characters.',
            'sortedBy.max'       => 'The sortedBy field must not exceed 255 characters.',
            'page.min'           => 'The page must be at least 1.',
            'limit.min'          => 'The limit must be at least 1.',
        ];
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
                $fail("The {$attribute} field is invalid.");

                return;
            }
        }
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
