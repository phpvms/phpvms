<?php

namespace App\Exceptions;

use App\Models\Page;

class UnknownPageType extends AbstractHttpException
{
    public function __construct(
        private readonly Page $page
    ) {
        parent::__construct(
            400,
            'Unknown page type "'.$page->type.'"'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'unknown-page-type';
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
        return [
            'id'   => $this->page->id,
            'type' => $this->page->type,
        ];
    }
}
