<?php

namespace App\Exceptions;

class CronInvalid extends AbstractHttpException
{
    public const MESSAGE = 'Cron ID is disabled or invalid';

    public function __construct()
    {
        parent::__construct(400, static::MESSAGE);
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'cron-invalid';
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
