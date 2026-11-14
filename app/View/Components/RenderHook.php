<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Fill;
use App\Features\Hooks\RenderHook as RenderHookPoint;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;
use Throwable;

/**
 * Renders the fills addons added to a render hook point.
 *
 *     <x-render-hook :hook="new \App\Features\Hooks\Points\App\Slots\SlotBidRowActions($bid, $flight)" />
 *
 * `view` and `html` fills render in list order; `vue` fills are for SPA themes
 * and are skipped. A view that throws is logged and left out, so one broken
 * addon cannot take the page down. A caller that already dispatched the hook
 * passes the resulting list as `fills` and the hook is not dispatched again.
 */
class RenderHook extends Component
{
    /**
     * @param list<Fill>|null $fills
     */
    public function __construct(
        public ?RenderHookPoint $hook = null,
        public ?array $fills = null,
    ) {}

    /**
     * The fills a Blade theme can draw.
     *
     * @param  list<Fill> $fills
     * @return list<Fill>
     */
    public static function renderable(array $fills): array
    {
        return array_values(array_filter($fills, fn (Fill $fill): bool => $fill->kind !== Fill::VUE));
    }

    public function render(): View
    {
        $fills = $this->fills ?? ($this->hook instanceof RenderHookPoint ? Hooks::filter($this->hook) : []);
        $rendered = [];

        foreach (self::renderable($fills) as $fill) {
            if ($fill->kind === Fill::HTML) {
                $rendered[] = (string) $fill->html;

                continue;
            }

            try {
                $rendered[] = view((string) $fill->view, $fill->data)->render();
            } catch (Throwable $throwable) {
                Log::warning(sprintf('Render hook "%s": view %s failed to render: %s', $this->hook instanceof RenderHookPoint ? $this->hook::name() : 'unknown', $fill->view, $throwable->getMessage()), [
                    'hook' => $this->hook instanceof RenderHookPoint ? $this->hook::name() : null,
                    'view' => $fill->view,
                ]);

                if (config('app.debug')) {
                    $rendered[] = sprintf('<div data-render-hook-error="%s">%s</div>', e($fill->view), e($throwable->getMessage()));
                }
            }
        }

        return view('components.render-hook', ['rendered' => $rendered]);
    }
}
