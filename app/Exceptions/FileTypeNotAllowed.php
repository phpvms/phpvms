<?php

namespace App\Exceptions;

class FileTypeNotAllowed extends AbstractHttpException
{
    public function __construct(string $extension)
    {
        parent::__construct(
            415,
            'Files with the extension "'.$extension.'" cannot be uploaded'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'file-type-not-allowed';
    }

    /**
     * Get the detailed error string
     */
    public function getErrorDetails(): string
    {
        return $this->getMessage();
    }

    /**
     * Return additional metadata
     */
    public function getErrorMetadata(): array
    {
        return [];
    }
}
