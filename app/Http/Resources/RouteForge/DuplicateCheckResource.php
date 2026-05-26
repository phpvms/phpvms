<?php

declare(strict_types=1);

namespace App\Http\Resources\RouteForge;

use App\Contracts\Resource;
use Illuminate\Http\Request;

/**
 * Wire shape returned by /admin/route-forge/api/check-duplicates.
 *
 * Wraps the array returned by DuplicateChecker::check() under a `duplicates`
 * key. The client expects a list (not the index-keyed map the service
 * returns internally), so array_values() collapses the keys here.
 *
 * Each entry shape (v2 — bundle-aware classification):
 *   {
 *     "index": int,                  // zero-based index into submitted rows
 *     "existing_flight_id": string,  // hash id of the conflicting flight
 *     "ident": string,               // human-readable, e.g. "JBU1900"
 *     "conflict_field": "flight_number",
 *     "severity": "error" | "warning",
 *     "kind": "same_bundle" | "cross_bundle",
 *     "existing_bundle_id": int,
 *     "existing_bundle_name": string
 *   }
 *
 * Severity classification mirrors L5 (same-bundle ERROR) and L12 (cross-
 * bundle WARNING). The frontend renders errors with a blocking red icon
 * and warnings with the existing yellow icon plus the bundle name.
 */
final class DuplicateCheckResource extends Resource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        /** @var array<int, array<string, mixed>> $duplicates */
        $duplicates = $this->resource;

        return [
            'duplicates' => array_values($duplicates),
        ];
    }
}
