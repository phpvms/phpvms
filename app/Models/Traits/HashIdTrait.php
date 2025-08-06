<?php

namespace App\Models\Traits;

use App\Support\Utils;
use Hashids\HashidsException;

trait HashIdTrait
{
    /**
     * @throws HashidsException
     */
    final protected static function createNewHashId(): string
    {
        return Utils::generateNewId();
    }

    /**
     * Register callbacks
     *
     * @throws HashidsException
     */
    final protected static function bootHashIdTrait(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = static::createNewHashId();
            }
        });
    }
}
