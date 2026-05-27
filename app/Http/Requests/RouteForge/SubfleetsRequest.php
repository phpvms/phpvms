<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteForge;

use App\Contracts\FormRequest;

/**
 * Query-string validation for /admin/route-forge/api/subfleets.
 *
 * The controller uses the validated `airline_id` to fetch every subfleet
 * attached to that airline (no capability filter v1 per design Decision 7).
 */
final class SubfleetsRequest extends FormRequest
{
    #[\Override]
    public function rules(): array
    {
        return [
            'airline_id' => ['required', 'integer', 'exists:airlines,id'],
        ];
    }
}
