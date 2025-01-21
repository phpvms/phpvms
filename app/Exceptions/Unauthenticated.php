<?php

namespace App\Exceptions;

class Unauthenticated extends AbstractHttpException
{
    public function __construct()
    {
        parent::__construct(
            401,
            'User not authenticated'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'unauthenticated';
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
