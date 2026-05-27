<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteForge;

use App\Http\Requests\SearchAirportsRequest;

/**
 * Query-string validation for /admin/route-forge/api/preview-airports.
 *
 * Extends the public SearchAirportsRequest (inheriting search/limit/hub/
 * orderBy/sortedBy rules) and adds two RouteForge-only filters consumed by
 * RouteForgeController::previewAirports():
 *
 *   - near: ICAO of an "origin" airport; controller stamps each result with
 *     `distance_from_origin_nm` (haversine in nautical miles).
 *   - max_range_nm: when present alongside `near`, controller also stamps
 *     `in_subfleet_range` (true iff distance ≤ max_range_nm).
 *
 * Inheriting from SearchAirportsRequest lets the controller pass `$this`
 * straight to `new AirportSearchQueryV1(...)` (typed against the parent
 * request) without changing the shared query class.
 */
final class PreviewAirportsRequest extends SearchAirportsRequest
{
    #[\Override]
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'near' => [
                'sometimes',
                'string',
                'size:4',
                'alpha',
                'exists:airports,id',
            ],
            'max_range_nm' => [
                'sometimes',
                'integer',
                'min:0',
                'max:20000',
            ],
        ]);
    }

    /**
     * Custom error messages for the RouteForge-specific filters layered on
     * top of the shared SearchAirportsRequest rules.
     *
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'near.size'            => __('filament.routeforge.validation.near_invalid'),
            'near.alpha'           => __('filament.routeforge.validation.near_invalid'),
            'near.exists'          => __('filament.routeforge.validation.near_invalid'),
            'max_range_nm.integer' => __('filament.routeforge.validation.max_range_nm_invalid'),
            'max_range_nm.min'     => __('filament.routeforge.validation.max_range_nm_invalid'),
            'max_range_nm.max'     => __('filament.routeforge.validation.max_range_nm_invalid'),
        ];
    }

    /**
     * Normalize `near` to uppercase ICAO so the `exists` rule and downstream
     * Airport lookup work regardless of how the client cased the input.
     */
    #[\Override]
    protected function prepareForValidation(): void
    {
        if ($this->filled('near')) {
            $this->merge([
                'near' => strtoupper(trim((string) $this->input('near'))),
            ]);
        }
    }
}
