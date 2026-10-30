<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Support\Skylight\Facades\Skylight;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;
use Throwable;

/**
 * Renders the Blade entries addons registered for a named slot.
 *
 *     <x-slot-outlet name="bids.row.actions" :context="['bid' => $bid]" />
 *
 * Each entry's `view` receives the context plus the entry's props; a prop that
 * starts with `@` is read from the context (e.g. `'@bid'`). Vue-only entries
 * are for SPA themes and are skipped. A view that throws is logged and left
 * out, so one broken addon cannot take the page down.
 */
class SlotOutlet extends Component
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $name,
        public array $context = [],
    ) {}

    public function render(): View
    {
        $rendered = [];

        foreach (Skylight::slots()->bladeEntriesFor($this->name) as $entry) {
            try {
                $rendered[] = view($entry['view'], [...$this->context, ...$this->resolveProps($entry['props'] ?? [])])->render();
            } catch (Throwable $throwable) {
                Log::warning(sprintf('Slot "%s": view %s failed to render: %s', $this->name, $entry['view'], $throwable->getMessage()), [
                    'slot'  => $this->name,
                    'entry' => $entry,
                ]);

                if (config('app.debug')) {
                    $rendered[] = sprintf('<div data-slot-error="%s">%s</div>', e($entry['view']), e($throwable->getMessage()));
                }
            }
        }

        return view('components.slot-outlet', ['rendered' => $rendered]);
    }

    /**
     * @param  array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function resolveProps(array $props): array
    {
        return array_map(
            fn (mixed $value): mixed => is_string($value) && str_starts_with($value, '@')
                ? data_get($this->context, substr($value, 1))
                : $value,
            $props,
        );
    }
}
