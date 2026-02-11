<?php

namespace App\Exceptions;

class BidNotFound extends AbstractHttpException
{
    public function __construct(
        private readonly int $bid_id
    ) {
        parent::__construct(
            404,
            'The bid '.$bid_id.' was not found'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'bid-not-found';
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
            'bid_id' => $this->bid_id,
        ];
    }
}
