<?php

namespace App\Exceptions;

/**
 * Prefile Error
 *
 * If listening to the prefile event message, use `throw new PrefileError("message message");`
 * to abort the prefile process and send the message up to ACARS
 */
class PrefileError extends AbstractHttpException
{
    public function __construct(string $error)
    {
        parent::__construct(400, $error);
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'prefile-error';
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
