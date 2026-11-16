<?php

declare(strict_types=1);

namespace App\Features\Hooks;

use App\Features\Hooks\Exceptions\DeniedByHook;

/**
 * The result of a gate: allowed, or denied with the reason and who denied it.
 * The caller decides what to do; authorize() throws for callers that want an error.
 */
final readonly class GateResponse
{
    private function __construct(
        private bool $allowed,
        private string $hook,
        private ?string $message = null,
        private ?string $listener = null,
        private ?string $addon = null,
        private ?int $status = null,
    ) {}

    public static function allow(string $hook): self
    {
        return new self(true, $hook);
    }

    public static function deny(string $hook, string $message, ?string $listener = null, ?string $addon = null, ?int $status = null): self
    {
        return new self(false, $hook, $message, $listener, $addon, $status);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function denied(): bool
    {
        return !$this->allowed;
    }

    /**
     * The stable dotted name of the gate, such as `bid.placing`.
     */
    public function hook(): string
    {
        return $this->hook;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * The listener class that denied, or null for an allow or a faked denial.
     */
    public function listener(): ?string
    {
        return $this->listener;
    }

    public function addon(): ?string
    {
        return $this->addon;
    }

    /**
     * The HTTP status to use when the denial is thrown; null means 422.
     */
    public function status(): ?int
    {
        return $this->status;
    }

    /**
     * @throws DeniedByHook when denied
     */
    public function authorize(): self
    {
        if ($this->denied()) {
            throw new DeniedByHook($this);
        }

        return $this;
    }
}
