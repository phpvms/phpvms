<?php

declare(strict_types=1);

namespace App\Features\Hooks;

/**
 * A hook point whose listeners may stop an action with a reason.
 *
 * A listener returns allow() or deny(). Every listener runs until one denies,
 * so the first denial wins over any allow. A listener that throws counts as an
 * allow, unless the hook point sets $failClosed.
 */
abstract class GateHook extends Hook
{
    /**
     * Treat a throwing listener as a denial instead of an allow.
     */
    protected bool $failClosed = false;

    private ?string $denial = null;

    private ?int $denialStatus = null;

    /**
     * @param int|null $status the HTTP status if the caller throws the denial; null means 422
     */
    public function deny(string $message, ?int $status = null): GateResponse
    {
        if ($this->denial === null) {
            $this->denial = $message;
            $this->denialStatus = $status;
        }

        return GateResponse::deny(static::name(), $message, status: $status);
    }

    /**
     * Allow for this listener only, so it can return early. Later listeners still run.
     */
    public function allow(): GateResponse
    {
        return GateResponse::allow(static::name());
    }

    public function denied(): bool
    {
        return $this->denial !== null;
    }

    public function denialMessage(): ?string
    {
        return $this->denial;
    }

    public function denialStatus(): ?int
    {
        return $this->denialStatus;
    }

    public function failsClosed(): bool
    {
        return $this->failClosed;
    }
}
