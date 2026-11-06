<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Features\Tiles\Tile as TileDefinition;
use App\Features\Tiles\Tiles;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use InvalidArgumentException;
use Livewire\Component as LivewireComponent;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Renders the body of one registered tile, and nothing else: no wrapper, card
 * or classes. The theme draws the frame.
 *
 *     <x-tile :tile="$tile" :with="['route' => $route]" />
 *     <x-tile id="weather" />
 *
 * Pass either `tile` or `id`, never both. An unknown id renders nothing, or
 * an HTML comment naming the id when `app.debug` is on.
 *
 * Class-based so the exclusive-props check, registry lookup and routing between
 * Livewire and view targets are plain PHP that can be tested without markup.
 */
class Tile extends Component
{
    public readonly ?TileDefinition $resolved;

    /**
     * @param array<string, mixed> $with Render-time data; wins over the tile's declared data.
     */
    public function __construct(
        ?TileDefinition $tile = null,
        public readonly ?string $id = null,
        public readonly array $with = [],
    ) {
        if ($tile instanceof TileDefinition !== ($id === null)) {
            throw new InvalidArgumentException('<x-tile> needs exactly one of [tile] or [id].');
        }

        $this->resolved = $tile ?? app(Tiles::class)->find((string) $id);
    }

    public function render(): View|Htmlable
    {
        $tile = $this->resolved;

        if ($tile === null) {
            return new HtmlString(config('app.debug') ? '<!-- tile ['.e($this->id).'] not found -->' : '');
        }

        $data = $tile->dataWith($this->with);

        if ($tile->view !== null) {
            return view($tile->view, $data);
        }

        $component = (string) $tile->livewire;

        return view('components.tile', [
            'component' => $component,
            'params'    => $this->acceptedParams($component, $data),
            'key'       => 'tile-'.$tile->id,
        ]);
    }

    /**
     * Livewire's own control params (`HandleComponents::isReservedParam()`),
     * kept whatever the component declares.
     */
    private const array RESERVED_PARAMS = ['lazy', 'defer', 'lazy.bundle', 'defer.bundle', 'wire:ref'];

    /**
     * Keep only the keys the Livewire component accepts: its `mount()` (and
     * trait `mount{Trait}()`) arguments and its own public properties, matched
     * the way Livewire matches them (a kebab-case key as camelCase), plus
     * Livewire's reserved control params such as `lazy`.
     *
     * Livewire forwards any other scalar key to the component's root element as
     * an HTML attribute, so a theme passing one shared `:with` to every tile
     * would write page data into the markup of tiles that never asked for it.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function acceptedParams(string $component, array $data): array
    {
        /** @var class-string<LivewireComponent> $class */
        $class = app('livewire.factory')->resolveComponentClass($component);
        $reflection = new ReflectionClass($class);

        $accepted = [];
        foreach (['mount', ...array_map(fn (string $trait): string => 'mount'.class_basename($trait), class_uses_recursive($class))] as $method) {
            if ($reflection->hasMethod($method)) {
                foreach (new ReflectionMethod($class, $method)->getParameters() as $parameter) {
                    $accepted[$parameter->getName()] = true;
                }
            }
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic() && $property->getDeclaringClass()->getName() !== LivewireComponent::class) {
                $accepted[$property->getName()] = true;
            }
        }

        return array_filter(
            $data,
            fn (string $key): bool => in_array($key, self::RESERVED_PARAMS, true)
                || str_starts_with($key, '@')
                || isset($accepted[str_contains($key, '-') ? Str::camel($key) : $key]),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
