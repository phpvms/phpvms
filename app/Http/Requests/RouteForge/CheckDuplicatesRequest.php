<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteForge;

use App\Contracts\FormRequest;
use Illuminate\Validation\Rule;

/**
 * JSON body validation for /admin/route-forge/api/check-duplicates.
 *
 * Body shape (top-level `airline_id` is the batch-wide constraint; rows must
 * match it):
 *
 *   {
 *     "airline_id": 1,
 *     "rows": [
 *       { "airline_id": 1, "flight_number": 100,
 *         "route_code": null, "route_leg": null,
 *         "dpt_airport_id": "KSFO", "arr_airport_id": "KLAX" },
 *       ...
 *     ]
 *   }
 *
 * Row count caps come from config('phpvms.routeforge.mesh_max_count', 100)
 * — the same hard cap that lint rule L10 enforces, kept centralized so the
 * Form Request, lint runner, and client all reference one source of truth.
 *
 * Cross-row constraint: every row's `airline_id` MUST equal the top-level
 * `airline_id` (Rule::in([...])). Prevents the client from mixing rows
 * across airlines through this endpoint, regardless of what the UI sent.
 */
final class CheckDuplicatesRequest extends FormRequest
{
    #[\Override]
    public function rules(): array
    {
        $maxRows = (int) config('phpvms.routeforge.mesh_max_count', 100);
        $batchAirlineId = $this->input('airline_id');

        return [
            'airline_id'            => ['required', 'integer', 'exists:airlines,id'],
            'rows'                  => ['required', 'array', 'min:1', 'max:'.$maxRows],
            'rows.*.airline_id'     => ['required', 'integer', Rule::in([$batchAirlineId])],
            'rows.*.flight_number'  => ['required', 'integer', 'min:1', 'max:9999'],
            'rows.*.route_code'     => ['nullable', 'string', 'max:5'],
            'rows.*.route_leg'      => ['nullable', 'integer', 'min:0', 'max:255'],
            'rows.*.dpt_airport_id' => ['required', 'string', 'size:4', 'alpha', 'exists:airports,id'],
            'rows.*.arr_airport_id' => ['required', 'string', 'size:4', 'alpha', 'exists:airports,id'],
        ];
    }

    /**
     * Normalize ICAOs to uppercase so `exists` rules and downstream
     * DuplicateChecker queries are case-insensitive against client input.
     */
    #[\Override]
    protected function prepareForValidation(): void
    {
        $rows = $this->input('rows', []);
        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach (['dpt_airport_id', 'arr_airport_id'] as $field) {
                if (isset($row[$field]) && is_string($row[$field])) {
                    $rows[$index][$field] = strtoupper(trim($row[$field]));
                }
            }
        }

        $this->merge(['rows' => $rows]);
    }
}
