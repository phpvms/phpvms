<?php

declare(strict_types=1);

namespace App\Exceptions;

class PilotIdRangeExhausted extends AbstractHttpException
{
    public const MESSAGE = 'No pilot ID is available within the configured range';

    public function __construct()
    {
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
        return 'pilot-id-range-exhausted';
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
        return [];
    }
}
