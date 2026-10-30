<?php

declare(strict_types=1);

namespace App\Features\Hooks;

/**
 * A hook point whose listeners may stop an action with a reason.
 *
 * A listener calls deny(); the dispatcher stops at the first denial. A listener
 * that throws counts as an allow, unless the hook point sets $failClosed.
 */
abstract class GateHook extends Hook
{
    /**
     * Treat a throwing listener as a denial instead of an allow.
     */
    protected bool $failClosed = false;

    private ?string $denial = null;

    public function deny(string $message): void
    {
        $this->denial ??= $message;
    }

    public function denied(): bool
    {
        return $this->denial !== null;
    }

    public function denialMessage(): ?string
    {
        return $this->denial;
    }

    public function failsClosed(): bool
    {
        return $this->failClosed;
    }
}
