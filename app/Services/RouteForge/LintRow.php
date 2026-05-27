<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Enums\FlightType;

/**
 * Typed view of a single submitted RouteForge batch row.
 *
 * Replaces the pre-cleanup `array<string, mixed>` shape that every lint rule
 * was reaching into via `$row['flight_number'] ?? null` etc. Hydrated once per
 * lint pass by `LintContextFactory::fromValidatedPayload()` and exposed on
 * `LintContext::$rows` as `list<LintRow>`.
 *
 * Defensive coercion: `fromArray` normalizes `''` / `0` / `'0'` to `null` for
 * nullable string fields (mirroring `StrictDuplicateKey::canonicalize`), and
 * casts numeric fields with safe `??` defaults so a partial wire payload
 * doesn't blow up rule reads.
 *
 * `$raw` escape-hatch: holds the original payload array so callers that still
 * need an array view (notably `StrictDuplicateKey::forRow`, which keeps its
 * array-input signature for this change) can pass it through without forcing
 * the key value object's signature to change. The trade-off: rules that
 * forget to type a new field can silently fall back to `$raw['foo']`. Treat
 * `$raw` as a transitional surface; remove once all consumers are typed.
 */
final readonly class LintRow
{
    /**
     * @param int                  $index Zero-based position in the submitted batch.
     * @param array<string, mixed> $raw   Original validated row array — escape hatch.
     */
    public function __construct(
        public int $index,
        public ?int $airlineId,
        public ?int $flightNumber,
        public ?string $routeCode,
        public ?int $routeLeg,
        public ?string $dptAirportId,
        public ?string $arrAirportId,
        public ?string $dptTimezone,
        public ?string $arrTimezone,
        public ?string $departureTime,
        public ?string $arrivalTime,
        public float $distanceNm,
        public int $flightTime,
        public ?int $days,
        public ?FlightType $flightType,
        public bool $enabled,
        public array $raw,
    ) {}

    /**
     * Hydrate a `LintRow` from a validated payload row.
     *
     * Caller supplies the row's zero-based `$index` because the lint envelope's
     * row list is positional (issues reference `row_index`); the array itself
     * doesn't carry the index.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(int $index, array $row): self
    {
        return new self(
            index: $index,
            airlineId: self::nullableInt($row['airline_id'] ?? null),
            flightNumber: self::nullableInt($row['flight_number'] ?? null),
            routeCode: self::canonicalizeString($row['route_code'] ?? null),
            routeLeg: self::nullableInt(self::canonicalizeString($row['route_leg'] ?? null)),
            dptAirportId: self::nullableString($row['dpt_airport_id'] ?? null),
            arrAirportId: self::nullableString($row['arr_airport_id'] ?? null),
            dptTimezone: self::nullableString($row['dpt_timezone'] ?? null),
            arrTimezone: self::nullableString($row['arr_timezone'] ?? null),
            departureTime: self::nullableString($row['departure_time'] ?? null),
            arrivalTime: self::nullableString($row['arrival_time'] ?? null),
            distanceNm: (float) ($row['distance_nm'] ?? 0),
            flightTime: (int) ($row['flight_time'] ?? 0),
            days: self::nullableInt($row['days'] ?? null),
            flightType: isset($row['flight_type'])
                ? FlightType::tryFrom((string) $row['flight_type'])
                : null,
            enabled: (bool) ($row['enabled'] ?? true),
            raw: $row,
        );
    }

    /**
     * Coerce `null` / `''` / `0` / `'0'` to canonical `null`; otherwise stringify.
     *
     * Mirrors `StrictDuplicateKey::canonicalize` so route_code / route_leg /
     * timezone fields land as `null` for the "absent" sentinel values the
     * wire payload commonly carries.
     */
    private static function canonicalizeString(mixed $value): ?string
    {
        if (in_array($value, [null, '', 0, '0'], true)) {
            return null;
        }

        return (string) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
