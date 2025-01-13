<?php

namespace App\Exceptions;

class ModuleExistsException extends AbstractHttpException
{
    public function __construct(string $module_name)
    {
        parent::__construct(
            409,
            'Module '.$module_name.' Already Exists!'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'module-already-exists';
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
