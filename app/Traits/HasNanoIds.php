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
}
