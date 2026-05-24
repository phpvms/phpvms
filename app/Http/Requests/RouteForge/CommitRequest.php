<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteForge;

use Illuminate\Validation\Rule;

/**
 * JSON body validation for /admin/route-forge/api/commit.
 *
 * Layers the commit-only fields onto the shared batch envelope:
 *
 *   - bundle.fare_multiplier: percent-string ("+10%", "-5%", "20%") stamped
 *     into flight_fare.price for each inherited subfleet fare. Empty / null
 *     means pure subfleet inheritance (no flight_fare rows created). Regex
 *     matches Decision 9.
 *   - on_conflict: reserved for the v1 "skip vs abort" path; the current
 *     RouteForgeService implementation always inserts and lint (L5) handles
 *     DB collisions as warnings. Kept in the wire shape for forward
 *     compatibility — defaults to 'abort' downstream.
 */
final class CommitRequest extends BaseRouteForgeBatchRequest
{
    #[\Override]
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'bundle.fare_multiplier' => ['nullable', 'string', 'regex:/^[+-]?\d+(\.\d+)?%$/'],
            'on_conflict'            => ['sometimes', 'string', Rule::in(['skip', 'abort'])],
        ]);
    }
}
