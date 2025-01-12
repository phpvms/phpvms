<?php

namespace App\Exceptions;

use App\Models\Pirep;

class PirepError extends AbstractHttpException
{
    public function __construct(
        private readonly Pirep $pirep,
        string $error
    ) {
        parent::__construct(400, $error);
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'pirep-error';
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
            'pirep_id' => $this->pirep->id,
        ];
    }
}
