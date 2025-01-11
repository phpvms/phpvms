<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Show an internal error, bug piggyback off of the validation
 * exception type - this has a place to show up in the UI as a
 * flash message.
 */
class InternalError extends ValidationException
{
    public const FIELD = 'internal_error_message';

    public const MESSAGE = '';

    /**
     * InternalError constructor.
     *
     * @param null $field
     */
    public function __construct(?string $message = null, $field = null)
    {
        Log::error($message);
        $validator = Validator::make([], []);
        $validator->errors()->add(
            $field ?? static::FIELD,
            $message ?? static::MESSAGE
        );

        parent::__construct($validator);
    }
}
