<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;

/**
 * Mount a Livewire component for a test and hand back the component itself,
 * typed as the class that was asked for.
 *
 * `Livewire::test(Foo::class)->instance()` is statically the base
 * `Livewire\Component`: `Testable` does carry `@template TComponent`, but the
 * facade's `@method static Testable test(...)` line drops it, and the manager's
 * own `@param class-string<TComponent>|TComponent|string|array $name` union
 * widens TComponent back to its `Component` bound. So calls to anything the
 * concrete page declares -- `getPreviewData()`, `getCachedHeaderActions()` --
 * are unresolvable at the call site. Restoring the link here keeps them
 * checkable rather than silently unverified.
 *
 * @template TComponent of Component
 *
 * @param  class-string<TComponent> $component
 * @param  array<string, mixed>     $params
 * @return TComponent
 */
function livewireInstance(string $component, array $params = []): Component
{
    $instance = Livewire::test($component, $params)->instance();

    if (!$instance instanceof $component) {
        throw new RuntimeException(sprintf(
            'Expected %s to mount, got %s.',
            $component,
            $instance::class,
        ));
    }

    return $instance;
}
