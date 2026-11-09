<?php

declare(strict_types=1);

namespace App\Features\Hooks\Exceptions;

use App\Exceptions\AbstractHttpException;

/**
 * A gate listener stopped an action. Rendered as RFC 7807 with type
 * `hook-denied`; the detail is the listener's message.
 */
class HookDenied extends AbstractHttpException
{
    public function __construct(
        string $message,
        public readonly string $hook,
        public readonly ?string $listener = null,
        public readonly ?string $addon = null,
    ) {
        parent::__construct(422, $message);
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
        return ['hook' => $this->hook];
    }
}
