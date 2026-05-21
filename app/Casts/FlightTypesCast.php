<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\FlightType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Cast for the comma-separated single-character `FlightType` list stored in
 * `subfleets.route_types` (e.g. `"C,F,J"`).
 *
 * Read: returns `Collection<int, FlightType>` (or NULL for unrestricted).
 * Write: accepts `null`, `Collection`, or `array<FlightType|string>`. Sorts
 *        alphabetically, deduplicates, joins with commas. Empty collapses to NULL.
 *
 * Invalid characters on read are logged (warning) and dropped. NULL is the
 * semantically-meaningful "unrestricted" value.
 */
class FlightTypesCast implements CastsAttributes
{
    /**
     * @param  Model|mixed                      $model
     * @param  mixed                            $value
     * @return Collection<int, FlightType>|null
     */
    public function get($model, string $key, $value, array $attributes): ?Collection
    {
        if ($value === null || $value === '') {
            return null;
        }

        $tokens = array_filter(
            array_map(trim(...), explode(',', (string) $value)),
            static fn (string $tok): bool => $tok !== '',
        );

        return collect($tokens)
            ->map(function (string $token) use ($model, $key): ?FlightType {
                $case = FlightType::tryFrom($token);
                if ($case === null) {
                    Log::warning('FlightTypesCast: dropping unknown FlightType value', [
                        'model' => $model::class,
                        'key'   => $key,
                        'value' => $token,
                    ]);
                }

                return $case;
            })
            ->filter()
            ->values();
    }

    /**
     * @param Model|mixed $model
     * @param mixed       $value
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        if (is_string($value)) {
            // Support direct string assignment (e.g. raw `"J,F,C"` from import).
            $value = array_filter(
                array_map(trim(...), explode(',', $value)),
                static fn (string $tok): bool => $tok !== '',
            );
        }

        if (!is_array($value) || $value === []) {
            return null;
        }

        $tokens = collect($value)
            ->map(static function ($item): string {
                if ($item instanceof FlightType) {
                    return $item->value;
                }

                return (string) $item;
            })
            ->filter(static fn (string $tok): bool => $tok !== '')
            ->unique()
            ->sort()
            ->values()
            ->implode(',');

        return $tokens === '' ? null : $tokens;
    }
}
