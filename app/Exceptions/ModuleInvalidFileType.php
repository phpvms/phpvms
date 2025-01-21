<?php

namespace App\Exceptions;

class ModuleInvalidFileType extends AbstractHttpException
{
    public function __construct()
    {
        parent::__construct(
            415,
            'The Module File Type is Invalid!'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'module-file-type-invalid';
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
