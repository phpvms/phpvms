<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\FormRequest;

/**
 * Validates query string for the public /users (pilots list) page.
 *
 * Backward compatible with the legacy Prettus RequestCriteria syntax:
 *   ?search=John                    free-text across name/email
 *   ?search=name:John               field-specific (LIKE %John%)
 *   ?search=name:John;email:foo     multi-field (OR)
 *
 * Fields:
 *   search     Free-text or `field:value[;field:value...]` (LIKE-style, max 255 chars)
 *   state      UserState integer (e.g. ACTIVE=1)
 *   airline_id Filter by airline
 *   orderBy    One of ORDERABLE_FIELDS (single-sort only — multi-sort syntax dropped)
 *   sortedBy   asc | desc
 *   page       Pagination page number (min 1)
 *   limit      Page size (1-100)
 *
 * Drops unsupported legacy params: ?with, ?withCount, ?filter, ?searchFields,
 * ?searchJoin (zero callers in production code; un-tested).
 */
class SearchUsersRequest extends FormRequest
{
    public const array ORDERABLE_FIELDS = [
        'id',
        'name',
        'email',
        'pilot_id',
        'callsign',
        'country',
        'airline_id',
        'rank_id',
        'home_airport_id',
        'curr_airport_id',
        'flights',
        'flight_time',
        'transfer_time',
        'created_at',
        'state',
        'vatsim_id',
        'ivao_id',
    ];

    public const array SORT_DIRECTIONS = ['asc', 'desc'];

    public function rules(): array
    {
        return [
            'search'     => ['sometimes', 'string', 'max:255'],
            'state'      => ['sometimes', 'integer'],
            'airline_id' => ['sometimes', 'integer'],
            'orderBy'    => ['sometimes', 'string', 'in:'.implode(',', self::ORDERABLE_FIELDS)],
            'sortedBy'   => ['sometimes', 'string', 'in:'.implode(',', self::SORT_DIRECTIONS)],
            'page'       => ['sometimes', 'integer', 'min:1'],
            'limit'      => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
