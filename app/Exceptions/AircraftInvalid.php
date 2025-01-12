<?php

namespace App\Exceptions;

use App\Models\Aircraft;

class AircraftInvalid extends AbstractHttpException
{
    public const MESSAGE = 'The supplied aircraft is invalid';

    public function __construct(
        private readonly Aircraft $aircraft
    ) {
        parent::__construct(
            400,
            static::MESSAGE
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'aircraft-invalid';
    }

    /**
     * Get the detailed error string.
     */
    public function getErrorDetails(): string
    {
        return $this->getMessage();
    }

    /**
     * Return an array with the error details, merged with the RFC7807 response.
     */
    public function getErrorMetadata(): array
    {
        return [
            'aircraft_id' => optional($this->aircraft)->id,
        ];
    }
}
