<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\FormRequest;

/**
 * Validates query string for the PIREP list endpoints:
 *   - /api/pireps           (Api/UserController::pireps)
 *   - /pireps               (Frontend/PirepController::index)
 *
 * Backward compatible with the legacy Prettus RequestCriteria syntax:
 *   ?search=1A2B3C                            free-text across id/flight_number
 *   ?search=user_id:42                        field-specific (=)
 *   ?search=user_id:42;state:2                multi-field (OR)
 *
 * Fields:
 *   search     Free-text or `field:value[;field:value...]` (max 255 chars)
 *   user_id    Filter by pilot
 *   state      PirepState integer
 *   status     PirepStatus string
 *   orderBy    One of ORDERABLE_FIELDS
 *   sortedBy   asc | desc
 *   page       Pagination page number (min 1)
 *   limit      Page size (1-100)
 *
 * Drops unsupported legacy params: ?with, ?withCount, ?filter, ?searchFields,
 * ?searchJoin (zero callers in production).
 */
class SearchPirepsRequest extends FormRequest
{
    public const array ORDERABLE_FIELDS = [
        'id',
        'flight_number',
        'user_id',
        'airline_id',
        'aircraft_id',
        'dpt_airport_id',
        'arr_airport_id',
        'flight_time',
        'state',
        'status',
        'submitted_at',
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
            'search'   => ['sometimes', 'string', 'max:255'],
            'user_id'  => ['sometimes', 'string'],
            'state'    => ['sometimes', 'integer'],
            'status'   => ['sometimes', 'string'],
            'orderBy'  => ['sometimes', 'string', 'in:'.implode(',', self::ORDERABLE_FIELDS)],
            'sortedBy' => ['sometimes', 'string', 'in:'.implode(',', self::SORT_DIRECTIONS)],
            'page'     => ['sometimes', 'integer', 'min:1'],
            'limit'    => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
