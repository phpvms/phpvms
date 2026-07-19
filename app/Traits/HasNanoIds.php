<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;

/**
 * Assigns Nano ID primary keys, mirroring Laravel's HasUuids/HasUlids traits.
 *
 * Builds on HasUniqueStringIds, so models using this trait automatically get a
 * non-incrementing string key, route-model-binding validation, and key
 * generation on create without declaring $keyType or $incrementing themselves.
 */
trait HasNanoIds
{
    use HasUniqueStringIds;

    /**
     * Generate a new Nano ID for the model.
     */
    public function newUniqueId(): string
    {
        return Str::nanoid();
    }

    /**
     * Determine if the given key is a valid Nano ID.
     */
    protected function isValidUniqueId($value): bool
    {
        return Str::isNanoid($value);
    }

    /**
     * Resolve route-model bindings without the strict Nano ID format gate.
     *
     * HasUniqueStringIds::resolveRouteBindingQuery() throws ModelNotFoundException
     * before it ever touches the database whenever the key fails
     * isValidUniqueId(). Our tables still hold legacy primary keys — v7 UUIDs and
     * older Nano IDs generated with a different alphabet/length — which are valid
     * rows that no longer match the current format. The gate would 404 every one
     * of them (e.g. every admin PIREP view/edit link for pre-migration records).
     * Skip the gate and let the database decide whether the row exists.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->where($field ?? $this->getRouteKeyName(), $value);
    }
}
