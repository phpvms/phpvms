<?php

namespace App\Exceptions;

use App\Models\Aircraft;

/**
 * Class AircraftNotAtAirport
 */
class AircraftNotAtAirport extends AbstractHttpException
{
    public const MESSAGE = 'The aircraft is not at the departure airport';

    public function __construct(
        private readonly Aircraft $aircraft
    ) {
        parent::__construct(
            400,
            static::MESSAGE
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'aircraft-not-at-airport';
    }

    /**
     * Get the detailed error string
     */
    public function getErrorDetails(): string
    {
        return $this->getMessage();
    }

    /**
     * Return an array with the error details, merged with the RFC7807 response
     */
    public function getErrorMetadata(): array
    {
        return [
            'aircraft_id' => $this->aircraft->id,
        ];
    }
}
