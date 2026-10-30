<?php

declare(strict_types=1);

namespace App\Features\Hooks\Testing;

use App\Features\Hooks\Exceptions\HookDenied;
use App\Features\Hooks\FilterHook;
use App\Features\Hooks\GateHook;
use App\Features\Hooks\Hook;
use App\Features\Hooks\HookDispatcher;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * Test double for the hook dispatcher, installed by `Hooks::fake()`.
 *
 * Records every dispatch and runs no listeners: filters return their value
 * unchanged, and gates allow unless a test forced a denial with deny().
 */
class HookFake extends HookDispatcher
{
    /**
     * @var list<Hook>
     */
    private array $dispatched = [];

    /**
     * @var array<class-string<GateHook>, string>
     */
    private array $denials = [];

    /**
     * Make a gate deny with the given message.
     *
     * @param class-string<GateHook> $hook
     */
    public function deny(string $hook, string $message): self
    {
        $this->denials[$hook] = $message;

        return $this;
    }

    public function filter(FilterHook $hook): mixed
    {
        $this->dispatched[] = $hook;

        return $hook->value;
    }

    public function gate(GateHook $hook): void
    {
        $this->dispatched[] = $hook;

        if (isset($this->denials[$hook::class])) {
            throw new HookDenied($this->denials[$hook::class], $hook::name());
        }
    }

    /**
     * @template T of Hook
     *
     * @param class-string<T>         $hook
     * @param (Closure(T): bool)|null $callback
     */
    public function assertDispatched(string $hook, ?Closure $callback = null): void
    {
        Assert::assertNotEmpty(
            $this->matching($hook, $callback),
            sprintf('The hook [%s] was not dispatched.', $hook),
        );
    }

    /**
     * @template T of Hook
     *
     * @param class-string<T>         $hook
     * @param (Closure(T): bool)|null $callback
     */
    public function assertNotDispatched(string $hook, ?Closure $callback = null): void
    {
        Assert::assertEmpty(
            $this->matching($hook, $callback),
            sprintf('The hook [%s] was dispatched.', $hook),
        );
    }

    /**
     * @template T of Hook
     *
     * @param  class-string<T>         $hook
     * @param  (Closure(T): bool)|null $callback
     * @return list<T>
     */
    private function matching(string $hook, ?Closure $callback): array
    {
        return array_values(array_filter(
            $this->dispatched,
            static fn (Hook $dispatched): bool => $dispatched instanceof $hook && (!$callback instanceof Closure || $callback($dispatched)),
        ));
    }
}
