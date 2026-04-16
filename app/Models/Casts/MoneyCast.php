<?php

namespace App\Models\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
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
        if ($value instanceof Money) {
            return $value;
        }

        return new Money($value);
    }

    /**
     * Transform the attribute to its underlying model values.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): float|int
    {
        $value = ($value instanceof Money)
            ? $value
            : new Money($value);

        return $value->getAmount();
    }
}
