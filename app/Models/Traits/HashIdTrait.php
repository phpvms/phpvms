<?php

namespace App\Models\Traits;

use App\Support\Utils;

trait HashIdTrait
{
    /**
     * @throws \Hashids\HashidsException
     */
    final protected static function createNewHashId(): string
    {
        return Utils::generateNewId();
    }

    /**
     * Register callbacks
     *
     * @throws \Hashids\HashidsException
     */
    final protected static function bootHashIdTrait(): void
    {
        static::creating(function ($model): void {
            if (empty($model->id)) {
                $model->id = static::createNewHashId();
            }
        });
    }
}
