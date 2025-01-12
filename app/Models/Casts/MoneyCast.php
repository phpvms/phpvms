<?php

namespace App\Models\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class MoneyCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param mixed                               $value
     *
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Money) {
            return $value;
        }

        return new Money($value);
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param mixed                               $value
     *
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        $value = ($value instanceof Money)
            ? $value
            : new Money($value);

        return $value ? (int) $value->getAmount() : null;
    }
}
