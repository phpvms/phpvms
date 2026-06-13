<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Support;

use App\Models\Flight;
use Stringable;

/**
 * Strict flight-duplicate key value object.
 *
 * Single source of truth for the 5-tuple
 * `(bundle_id, airline_id, flight_number, route_code, route_leg)`
 * used by L4 (intra-batch duplicate) and `ExistingDuplicates` (L5 same-bundle
 * + L12 cross-bundle existing-flight collisions, both emitted from one
 * merged rule).
 *
 * String form: `bundleId|airlineId|flightNumber|routeCode|routeLeg`
 * with empty string for null `routeCode` / `routeLeg`.
 *
 * Bundle-id semantics: production storage uses non-null `bundle_id` (NOT NULL FK).
 * During commit-of-new-bundle the lint context's bundle is unsaved and `id` is
 * null; the factories accept `?int $bundleId` and coalesce to `0` for key
 * construction. Callers that need same-bundle scoping (L5) MUST short-circuit
 * on null bundleId before constructing keys — a key with bundleId=0 would only
 * collide with another bundleId=0 (intra-batch), which is the L4 use case.
 *
 * Canonicalization: factories coerce null / `''` / `0` / `'0'` for routeCode
 * and routeLeg to canonical `null`. Production data is canonicalized at the
 * Flight model boundary (mutators) after the v2 dedup migration; this defensive
 * normalization keeps callers safe when hydrating from raw POST payloads.
 *
 * Co-owned with the `routeforge-lint-cleanup` change; whichever change ships
 * first lays down this class, the other consumes it.
 */
final readonly class StrictDuplicateKey implements Stringable
{
    public function __construct(
        public int $bundleId,
        public int $airlineId,
        public int $flightNumber,
        public ?string $routeCode,
        public ?string $routeLeg,
    ) {}

    /**
     * Build the strict key from a submitted row array.
     *
     * The row carries the per-row fields (airline_id, flight_number,
     * route_code, route_leg); bundle_id is a batch-wide value passed in by the
     * caller (typically `$ctx->bundle->id`).
     *
     * @param array<string, mixed> $row
     */
    public static function forRow(array $row, ?int $bundleId): self
    {
        return new self(
            bundleId: $bundleId ?? 0,
            airlineId: (int) ($row['airline_id'] ?? 0),
            flightNumber: (int) ($row['flight_number'] ?? 0),
            routeCode: self::canonicalize($row['route_code'] ?? null),
            routeLeg: self::canonicalize($row['route_leg'] ?? null),
        );
    }

    /**
     * Build the strict key from a persisted Flight model.
     *
     * Used by `ExistingDuplicates` to index existing-DB matches. The
     * Flight's bundle_id is non-null in production storage (NOT NULL FK).
     */
    public static function forFlight(Flight $flight): self
    {
        return new self(
            bundleId: (int) $flight->bundle_id,
            airlineId: (int) $flight->airline_id,
            flightNumber: (int) $flight->flight_number,
            routeCode: self::canonicalize($flight->route_code),
            routeLeg: self::canonicalize($flight->route_leg),
        );
    }

    /**
     * Stringify into the canonical key form used as an array index in
     * bulk-indexing operations.
     */
    public function __toString(): string
    {
        return implode('|', [
            (string) $this->bundleId,
            (string) $this->airlineId,
            (string) $this->flightNumber,
            $this->routeCode ?? '',
            $this->routeLeg ?? '',
        ]);
    }

    /**
     * Partial key used by L12 for cross-bundle airline+flight-only matching.
     *
     * Cross-bundle conflicts ignore route_code / route_leg because the
     * operational concern is "is this flight number already in use anywhere
     * for this airline?" not "exact tuple collision".
     */
    public static function crossBundleKey(int $airlineId, int $flightNumber): string
    {
        return $airlineId.'|'.$flightNumber;
    }

    /**
     * Bulk-index an iterable of items by a key extractor returning a
     * `StrictDuplicateKey` instance.
     *
     * Convenience helper so callers don't repeat the foreach + (string) cast
     * pattern. Last-write-wins on collisions (caller's responsibility if
     * uniqueness matters during indexing).
     *
     * @template T
     *
     * @param  iterable<T>                $items
     * @param  callable(T): (self|string) $keyer
     * @return array<string, T>
     */
    public static function index(iterable $items, callable $keyer): array
    {
        $byKey = [];
        foreach ($items as $item) {
            $byKey[(string) $keyer($item)] = $item;
        }

        return $byKey;
    }

    /**
     * Collapse null / empty-string / zero-equivalent values to canonical null.
     *
     * Production storage is canonicalized at the Flight model boundary
     * (mutators) after the v2 dedup migration. This defensive pass keeps the
     * value object safe when hydrating from raw POST payloads, where the
     * client might submit '' or '0' for an "absent" route_code.
     */
    private static function canonicalize(mixed $value): ?string
    {
        if (in_array($value, [null, '', 0, '0'], true)) {
            return null;
        }

        return (string) $value;
    }
}
