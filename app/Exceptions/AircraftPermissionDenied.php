<?php

namespace App\Exceptions;

use App\Models\Aircraft;
use App\Models\User;

class AircraftPermissionDenied extends AbstractHttpException
{
    public const MESSAGE = 'User is not allowed to fly this aircraft';

    public function __construct(
        private readonly User $user,
        private readonly Aircraft $aircraft
    ) {
        parent::__construct(
            400,
            static::MESSAGE
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'aircraft-permission-denied';
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
            'aircraft_id' => $this->aircraft->id,
            'user_id'     => $this->user->id,
        ];
    }
}
