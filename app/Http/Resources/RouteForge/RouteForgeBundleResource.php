<?php

declare(strict_types=1);

namespace App\Http\Resources\RouteForge;

use App\Contracts\Resource;
use App\Models\FlightBundle;
use Illuminate\Http\Request;

/**
 * Wire shape returned by /admin/route-forge/api/bundles.
 *
 * Drives the existing-bundle picker in the SPA's BundleConfigSection. Date
 * fields are serialized as `YYYY-MM-DD` strings (or null) so the read-only
 * summary can render them without parsing.
 *
 * Soft-deleted rows are excluded by the model's SoftDeletes scope upstream
 * of this resource — this class assumes the resource it wraps is already
 * a live row.
 *
 * @phpstan-type BundleSummary array{
 *     id: int,
 *     name: string,
 *     description: string|null,
 *     enabled: bool,
 *     start_date: string|null,
 *     end_date: string|null,
 * }
 */
final class RouteForgeBundleResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        /** @var FlightBundle $bundle */
        $bundle = $this->resource;

        return [
            'id'          => (int) $bundle->id,
            'name'        => (string) $bundle->name,
            'description' => $bundle->description,
            'enabled'     => (bool) $bundle->enabled,
            'start_date'  => $bundle->start_date?->format('Y-m-d'),
            'end_date'    => $bundle->end_date?->format('Y-m-d'),
        ];
    }
}
