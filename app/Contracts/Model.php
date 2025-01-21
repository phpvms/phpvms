<?php

namespace App\Contracts;

/**
 * @property mixed $id
 * @property bool  $skip_mutator
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
abstract class Model extends \Illuminate\Database\Eloquent\Model
{
    /**
     * Max length of ID for string columns
     */
    public const ID_MAX_LENGTH = 16;

    /**
     * For the factories, skip the mutators. Only apply to one instance
     */
    public $skip_mutator = false;
}
