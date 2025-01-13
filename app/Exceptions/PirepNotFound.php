<?php

namespace App\Exceptions;

class PirepNotFound extends AbstractHttpException
{
    public function __construct(
        private readonly string $pirep_id
    ) {
        parent::__construct(
            404,
            'PIREP '.$pirep_id.' not found'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'pirep-not-found';
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
            'pirep_id' => $this->pirep_id,
        ];
    }
}
