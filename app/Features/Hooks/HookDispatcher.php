<?php

declare(strict_types=1);

namespace App\Features\Hooks;

use App\Features\Hooks\Attributes\HookPriority;
use App\Features\Hooks\Exceptions\HookDenied;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

/**
 * Runs filter and gate listeners for typed hook points.
 *
 * Octane: a singleton that only holds boot-time registrations. Hook objects are
 * created per call, and listeners are resolved from the container on every
 * dispatch, so nothing carries over between requests.
 */
class HookDispatcher
{
    /**
     * @var array<class-string<Hook>, list<array{listener: class-string, addon: ?string, priority: int, sequence: int}>>
     */
    private array $listeners = [];

    private int $sequence = 0;

    public function __construct(private readonly Container $container) {}

    /**
     * Register a listener for a hook point.
     *
     * @param class-string $hook
     * @param class-string $listener
     */
    public function listen(string $hook, string $listener, ?string $addon = null): void
    {
        if (!is_subclass_of($hook, FilterHook::class) && !is_subclass_of($hook, GateHook::class)) {
            throw new InvalidArgumentException(sprintf(
                'Hook listener %s%s targets %s, which is not a filter or gate hook point.',
                $listener,
                $addon !== null ? ' (addon '.$addon.')' : '',
                $hook,
            ));
        }

        $this->listeners[$hook][] = [
            'listener' => $listener,
            'addon'    => $addon,
            'priority' => $this->priorityOf($listener),
            'sequence' => $this->sequence++,
        ];
    }

    /**
     * Registered listeners for a hook point, in run order.
     *
     * @param  class-string                                                                      $hook
     * @return list<array{listener: class-string, addon: ?string, priority: int, sequence: int}>
     */
    public function listenersFor(string $hook): array
    {
        $listeners = $this->listeners[$hook] ?? [];

        usort($listeners, static fn (array $a, array $b): int => [$a['priority'], $a['sequence']] <=> [$b['priority'], $b['sequence']]);

        return $listeners;
    }

    /**
     * Pass the hook's value through every listener and return the result.
     */
    public function filter(FilterHook $hook): mixed
    {
        foreach ($this->listenersFor($hook::class) as $entry) {
            $previous = $hook->value;
            $hook->value = is_object($previous) ? clone $previous : $previous;

            try {
                $result = $this->call($entry['listener'], $hook);
            } catch (Throwable $throwable) {
                $hook->value = $previous;
                $this->report('warning', $hook, $entry, 'threw: '.$throwable->getMessage());

                continue;
            }

            // A render hook listener changes `$hook->value` in place and returns nothing.
            $candidate = $hook instanceof RenderHook ? $hook->value : $result;

            if (!$hook->accepts($candidate)) {
                $hook->value = $previous;
                $this->report('warning', $hook, $entry, ($hook instanceof RenderHook ? 'left ' : 'returned ').get_debug_type($candidate).', which the hook does not accept');

                continue;
            }

            $hook->value = $candidate;
        }

        return $hook->value;
    }

    /**
     * Consult every gate listener until one denies.
     *
     * @throws HookDenied
     */
    public function gate(GateHook $hook): void
    {
        foreach ($this->listenersFor($hook::class) as $entry) {
            try {
                $this->call($entry['listener'], $hook);
            } catch (HookDenied $denied) {
                throw $denied;
            } catch (Throwable $throwable) {
                $this->report('error', $hook, $entry, 'threw: '.$throwable->getMessage());

                // A listener that denied before it threw still denied; fall through
                // so the denial is reported against this listener, not the next.
                if ($hook->failsClosed() && !$hook->denied()) {
                    throw new HookDenied(__('This action is not available right now.'), $hook::name(), $entry['listener'], $entry['addon']);
                }
            }

            if ($hook->denied()) {
                throw new HookDenied((string) $hook->denialMessage(), $hook::name(), $entry['listener'], $entry['addon']);
            }
        }
    }

    private function call(string $listener, Hook $hook): mixed
    {
        $instance = $this->container->make($listener);

        return method_exists($instance, 'handle') ? $instance->handle($hook) : $instance($hook);
    }

    private function priorityOf(string $listener): int
    {
        if (!class_exists($listener)) {
            return HookPriority::DEFAULT;
        }

        $attribute = new ReflectionClass($listener)->getAttributes(HookPriority::class)[0] ?? null;

        return $attribute?->newInstance()->priority ?? HookPriority::DEFAULT;
    }

    /**
     * @param array{listener: class-string, addon: ?string, priority: int, sequence: int} $entry
     */
    private function report(string $level, Hook $hook, array $entry, string $problem): void
    {
        Log::log($level, sprintf('Hook listener %s for %s %s', $entry['listener'], $hook::name(), $problem), [
            'hook'     => $hook::name(),
            'listener' => $entry['listener'],
            'addon'    => $entry['addon'],
        ]);
    }
}
