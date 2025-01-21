<?php

namespace App\Exceptions;

use Exception;

class AssetNotFound extends AbstractHttpException
{
    public function __construct(
        private readonly Exception $exception
    ) {
        parent::__construct(
            404,
            $exception->getMessage()
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'not-found';
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
        return [];
    }
}
