<?php

namespace App\Exceptions;

use App\Models\Pirep;

class PirepCancelled extends AbstractHttpException
{
    public function __construct(
        private readonly Pirep $pirep
    ) {
        parent::__construct(
            400,
            'PIREP has been cancelled, updates are not allowed'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'pirep-cancelled';
    }

    public function getErrorDetails(): string
    {
        return $this->getMessage();
    }

    public function getErrorMetadata(): array
    {
        return [
            'pirep_id' => $this->pirep->id,
        ];
    }
}
