<?php

declare(strict_types=1);

namespace App\Features\Dashboard\Livewire;

use App\Features\Dashboard\WidgetCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The dashboard toolbar and widget board.
 *
 * The layout lives in the browser (localStorage `airside.dashboard.layout.v1`).
 * The view's script sends it with `loadLayout()` on mount, and listens for
 * `dashboard-layout-changed` to save each change, so the server renders only
 * the widgets that are placed. Drag is handled in the view with native drag
 * events and ends in `moveWidget()`.
 */
class DashboardBoard extends Component
{
    /**
     * The nav display route: origin and destination as on `DashboardData::$route`.
     *
     * @var array{from?: array<string, mixed>|null, to?: array<string, mixed>|null}
     */
    #[Locked]
    public array $route = [];

    public bool $ready = false;

    public bool $editing = false;

    /** @var list<string> */
    public array $grid = [];

    /** @var list<string> */
    public array $sidebar = [];

    /**
     * @param array{from?: array<string, mixed>|null, to?: array<string, mixed>|null} $route
     */
    public function mount(array $route = []): void
    {
        $this->route = $route;
    }

    /**
     * Take the layout the browser saved. A missing or broken layout gives the default.
     *
     * @param array<mixed>|null $layout
     */
    public function loadLayout(?array $layout = null): void
    {
        $this->applyLayout(WidgetCatalog::sanitize($layout));
        $this->ready = true;
    }

    public function toggleEditing(): void
    {
        $this->editing = !$this->editing;
    }

    public function addWidget(string $id): void
    {
        $widget = WidgetCatalog::find($id);

        if ($widget === null || in_array($id, $this->placedIds(), true)) {
            return;
        }

        $zone = $widget['zone'];
        $this->{$zone}[] = $id;

        $this->layoutChanged();
    }

    public function removeWidget(string $id): void
    {
        $this->grid = array_values(array_diff($this->grid, [$id]));
        $this->sidebar = array_values(array_diff($this->sidebar, [$id]));

        $this->layoutChanged();
    }

    public function resetLayout(): void
    {
        $this->applyLayout(WidgetCatalog::defaultLayout());
        $this->layoutChanged();
    }

    /**
     * The `wire:sort` handler: a widget was dropped at a position in a zone.
     */
    public function sortWidget(string $id, int $position, string $zone): void
    {
        $this->moveWidget($id, $zone, $position);
    }

    /**
     * Put a widget at a position in a zone (drag and drop).
     */
    public function moveWidget(string $id, string $zone, int $index): void
    {
        if (WidgetCatalog::find($id) === null || !in_array($zone, [WidgetCatalog::ZONE_GRID, WidgetCatalog::ZONE_SIDEBAR], true)) {
            return;
        }

        $this->grid = array_values(array_diff($this->grid, [$id]));
        $this->sidebar = array_values(array_diff($this->sidebar, [$id]));

        $ids = $this->{$zone};
        array_splice($ids, max(0, min($index, count($ids))), 0, [$id]);
        $this->{$zone} = $ids;

        $this->layoutChanged();
    }

    /**
     * The widgets that are not placed (the Add widget menu).
     *
     * @return list<array{id: string, title: string, icon: string, zone: string, span: int, default: bool}>
     */
    #[Computed]
    public function availableWidgets(): array
    {
        $placed = $this->placedIds();

        return array_values(array_filter(
            WidgetCatalog::all(),
            fn (array $widget): bool => !in_array($widget['id'], $placed, true),
        ));
    }

    public function render(): View
    {
        return view('livewire.dashboard.dashboard-board', [
            'catalog' => WidgetCatalog::all(),
        ]);
    }

    /**
     * @param array{grid: list<string>, sidebar: list<string>} $layout
     */
    private function applyLayout(array $layout): void
    {
        $this->grid = $layout['grid'];
        $this->sidebar = $layout['sidebar'];
    }

    /**
     * @return list<string>
     */
    private function placedIds(): array
    {
        return [...$this->grid, ...$this->sidebar];
    }

    /**
     * Tell the browser to save the layout.
     */
    private function layoutChanged(): void
    {
        $this->dispatch('dashboard-layout-changed', grid: $this->grid, sidebar: $this->sidebar);
    }
}
