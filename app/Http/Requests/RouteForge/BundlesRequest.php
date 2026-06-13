<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteForge;

use App\Contracts\FormRequest;
use Override;

/**
 * Query-string validation for /admin/route-forge/api/bundles.
 *
 * The endpoint is a paginated/searchable picker feed for the SPA's
 * BundleConfigSection. All inputs are optional; an empty query returns the
 * first page of bundles ordered by name. Per-page is capped at 100 to keep
 * the picker payload bounded; the SPA debounces typeahead so even active
 * use cases settle well below that ceiling.
 */
final class BundlesRequest extends FormRequest
{
    #[Override]
    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:255'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
