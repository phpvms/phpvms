<?php

namespace App\Exceptions;

use App\Models\Flight;

class DuplicateFlight extends AbstractHttpException
{
    public function __construct(
        private readonly Flight $flight
    ) {
        parent::__construct(
            409,
            'Duplicate flight with same number/code/leg found'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'duplicate-flight';
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
            'flight_id' => $this->flight->id,
        ];
    }
}
