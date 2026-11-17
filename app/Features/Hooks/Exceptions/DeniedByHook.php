<?php

declare(strict_types=1);

namespace App\Features\Hooks\Exceptions;

use App\Exceptions\AbstractHttpException;
use App\Features\Hooks\GateResponse;

/**
 * A gate denied an action and the caller chose to stop with an error.
 * Thrown by GateResponse::authorize(); rendered as RFC 7807 with type `hook-denied`.
 */
class DeniedByHook extends AbstractHttpException
{
    public function __construct(public readonly GateResponse $response)
    {
        parent::__construct($response->status() ?? 422, (string) $response->message());
    }

    public function getErrorType(): string
    {
        return 'hook-denied';
    }

    public function getErrorDetails(): string
    {
        return $this->getMessage();
    }

    public function getErrorMetadata(): array
    {
        return ['hook' => $this->response->hook()];
    }
}
