<?php

namespace App\Exceptions;

use App\Models\Pirep;

class PirepCancelNotAllowed extends AbstractHttpException
{
    public function __construct(
        private readonly Pirep $pirep
    ) {
        parent::__construct(
            400,
            'This PIREP can\'t be cancelled'
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root).
     */
    public function getErrorType(): string
    {
        return 'pirep-cancel-not-allowed';
    }

    public function getErrorDetails(): string
    {
        return $this->getMessage();
    }

    public function getErrorMetadata(): array
    {
        return [
            'pirep_id' => $this->pirep->id,
            'state'    => $this->pirep->state,
        ];
    }
}
