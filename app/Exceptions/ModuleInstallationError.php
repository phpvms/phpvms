<?php

namespace App\Exceptions;

class ModuleInstallationError extends AbstractHttpException
{
    public function __construct()
    {
        parent::__construct(
            500,
            'Installation of Module Failed!'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'module-installation-error';
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
