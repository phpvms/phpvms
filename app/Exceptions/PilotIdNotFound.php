<?php

namespace App\Exceptions;

class PilotIdNotFound extends AbstractHttpException
{
    public function __construct(
        private readonly string $pilot_id
    ) {
        parent::__construct(
            404,
            'Pilot '.$pilot_id.' not found'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'pilot-id-not-found';
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
            'pilot_id' => $this->pilot_id,
        ];
    }
}
