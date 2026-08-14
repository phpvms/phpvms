<?php

declare(strict_types=1);

namespace App\Filament\Resources\Fares\Support;

use App\Models\Fare;
use App\Services\FareService;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Provenance for one fare through the override cascade (base → subfleet
 * pivot → flight pivot, flight wins). The math is delegated to
 * FareService::getFareWithPivot on replicated fares — percentage strings,
 * capacity flooring and the flight pivot's missing auto-price columns all
 * behave exactly as they do when a pilot is charged, so the trace cannot
 * disagree with production pricing.
 */
final class FareTrace
{
    public const array FIELDS = ['price', 'cost', 'capacity'];

    public const array AUTO_FIELDS = ['base_price', 'per_nm', 'multiplier'];

    /**
     * @return array<string, array{base: mixed, subfleet: array{raw: string, value: mixed}|null, flight: array{raw: string, value: mixed}|null, value: mixed, source: 'base'|'subfleet'|'flight'}>
     */
    public static function resolve(Fare $fare, ?Pivot $subfleetPivot = null, ?Pivot $flightPivot = null): array
    {
        $service = app(FareService::class);

        $afterSubfleet = $fare->replicate();
        if ($subfleetPivot instanceof Pivot) {
            $afterSubfleet = $service->getFareWithPivot($afterSubfleet, $subfleetPivot);
        }

        $afterFlight = $afterSubfleet->replicate();
        if ($flightPivot instanceof Pivot) {
            $afterFlight = $service->getFareWithPivot($afterFlight, $flightPivot);
        }

        $trace = [];

        foreach (self::FIELDS as $field) {
            $subfleetRaw = $subfleetPivot?->{$field};
            $flightRaw = $flightPivot?->{$field};

            $trace[$field] = [
                'base'     => $fare->{$field},
                'subfleet' => filled($subfleetRaw) ? ['raw' => (string) $subfleetRaw, 'value' => $afterSubfleet->{$field}] : null,
                'flight'   => filled($flightRaw) ? ['raw' => (string) $flightRaw, 'value' => $afterFlight->{$field}] : null,
                'value'    => $afterFlight->{$field},
                'source'   => filled($flightRaw) ? 'flight' : (filled($subfleetRaw) ? 'subfleet' : 'base'),
            ];
        }

        // Auto-price inputs can only be overridden per subfleet — the
        // flight_fare pivot has no columns for them.
        foreach (self::AUTO_FIELDS as $field) {
            $raw = $subfleetPivot->{$field} ?? null;

            $trace[$field] = [
                'base'     => $fare->{$field},
                'subfleet' => filled($raw) ? ['raw' => (string) $raw, 'value' => $afterSubfleet->{$field}] : null,
                'flight'   => null,
                'value'    => $afterSubfleet->{$field},
                'source'   => filled($raw) ? 'subfleet' : 'base',
            ];
        }

        return $trace;
    }

    /** Whether a pivot row actually changes anything, or is a bare attachment. */
    public static function pivotOverrides(Pivot $pivot): bool
    {
        return array_any([...self::FIELDS, ...self::AUTO_FIELDS], fn (string $field): bool => filled($pivot->{$field} ?? null));
    }
}
