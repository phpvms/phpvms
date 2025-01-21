<?php

namespace App\Exceptions;

use App\Models\User;

class UserBidLimit extends AbstractHttpException
{
    public function __construct(
        private readonly User $user
    ) {
        parent::__construct(
            409,
            'User '.$user->ident.' has the maximum number of bids'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'user-bid-limit';
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
        return [
            'user_id' => $this->user->id,
        ];
    }
}
