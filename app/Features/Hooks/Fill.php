<?php

declare(strict_types=1);

namespace App\Features\Hooks;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * One piece of content an addon puts into a render hook point.
 *
 * Three kinds: a Blade `view` (with data), raw `html`, or a `vue` component for
 * the SPA. Only `vue` fills can reach the SPA; see {@see self::toSpaEntry()}.
 */
final readonly class Fill
{
    public const string VIEW = 'view';

    public const string HTML = 'html';

    public const string VUE = 'vue';

    /**
     * @param array<string, mixed> $props
     */
    private function __construct(
        public string $kind,
        public ?string $view = null,
        public ?string $html = null,
        public ?string $component = null,
        public ?string $module = null,
        public array $props = [],
        public array $data = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function view(string $view, array $data = []): self
    {
        return new self(self::VIEW, view: $view, data: $data);
    }

    public static function html(Htmlable|string $html): self
    {
        return new self(self::HTML, html: $html instanceof Htmlable ? $html->toHtml() : $html);
    }

    /**
     * @param string               $component Resolver key of the Vue component.
     * @param string|null          $module    URL of a pre-built ESM module for a third-party component.
     * @param array<string, mixed> $props     JSON-serialisable values or `@ref` strings; no Eloquent models.
     *
     * @throws InvalidArgumentException when `module` has no `component`, or `props` holds a model
     */
    public static function vue(string $component, ?string $module = null, array $props = []): self
    {
        if ($module !== null && $module !== '' && $component === '') {
            throw new InvalidArgumentException('A vue fill with a "module" requires a "component" (the resolver key it is imported under).');
        }

        if (self::containsModel($props)) {
            throw new InvalidArgumentException('Props of a vue fill must not contain Eloquent models; pass a DTO or an "@ref" string instead.');
        }

        return new self(self::VUE, component: $component, module: $module, props: $props);
    }

    /**
     * The entry shape `SlotRegistry::all()` gives the SPA.
     *
     * @return array{slot: string, component: ?string, module: ?string, view: null, props: array<string, mixed>, order: int}
     *
     * @throws LogicException when this is not a vue fill
     */
    public function toSpaEntry(string $slot, int $order): array
    {
        if ($this->kind !== self::VUE) {
            throw new LogicException(sprintf('Only a vue fill can be sent to the SPA; this is a "%s" fill.', $this->kind));
        }

        return [
            'slot'      => $slot,
            'component' => $this->component,
            'module'    => $this->module,
            'view'      => null,
            'props'     => $this->props,
            'order'     => $order,
        ];
    }

    private static function containsModel(mixed $value): bool
    {
        if ($value instanceof Model) {
            return true;
        }

        return is_array($value) && array_any($value, self::containsModel(...));
    }
}
