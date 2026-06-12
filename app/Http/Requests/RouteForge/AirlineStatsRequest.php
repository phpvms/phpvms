<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteForge;

use App\Contracts\FormRequest;
use Override;

/**
 * Query-string validation for /admin/route-forge/api/airline-stats.
 *
 * Returns the snapshot the L1 capacity hint and origin-picker affordance
 * read on the client.
 */
final class AirlineStatsRequest extends FormRequest
{
    #[Override]
    public function rules(): array
    {
        return [
            'airline_id' => ['required', 'integer', 'exists:airlines,id'],
        ];
    }
}
