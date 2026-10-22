<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast into a Carbon DateTime instance
 */
class CarbonCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  Model $model
     * @param  mixed $value
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        // `new Carbon(null)` is *now*, not null — so without this an unset
        // column reads back as the current time. It went unnoticed while the
        // only nullable one, `pireps.submitted_at`, was never shown for an
        // unfiled PIREP: a flight still in the air reported itself as filed
        // this second.
        if ($value === null) {
            return null;
        }

        return new Carbon($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        if (is_string($value)) {
            return new Carbon($value)->toDateTimeString();
        }

        return $value;
    }
}
