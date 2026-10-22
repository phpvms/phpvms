<?php

declare(strict_types=1);

namespace App\Filament\Resources\MapLayers\Pages;

use App\Filament\Actions\Drawer;
use App\Filament\Resources\MapLayers\MapLayerResource;
use App\Filament\Resources\MapLayers\Schemas\BasemapForm;
use App\Models\MapLayer;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListMapLayers extends ListRecords
{
    protected static string $resource = MapLayerResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->basemapsAction(),
            CreateAction::make()
                ->icon(Phosphor::PlusCircleLight),
        ];
    }

    /**
     * The basemap pair is one row of its own type rather than a pair of
     * settings, so it gets a header action instead of a table row: it is the
     * map's own style, not an overlay to be listed alongside OpenAIP or METAR.
     * Routed through {@see Drawer} so it wears the same branded slide-over
     * chrome as every other settings editor in the panel.
     */
    private function basemapsAction(): Action
    {
        return Drawer::configure(
            Action::make('basemaps')
                // REQUIRED. This action creates or updates a `map_layers` row, so
                // viewing the page is not sufficient authority. Filament
                // auto-authorizes its own CreateAction/EditAction against the
                // policy, but a hand-rolled Action gets nothing unless it asks —
                // without this, anyone with `view:map-layer` could change the
                // basemap for the whole site.
                ->authorize('update', MapLayer::class)
                ->label(__('map.basemaps'))
                ->icon(Phosphor::StackLight)
                ->color('gray')
                ->modalHeading(__('map.basemaps'))
                ->modalDescription(__('map.basemaps_hint'))
                // Lazily, not a literal array: the drawer re-fills on each open,
                // so a save must be reflected the next time it is opened.
                ->fillForm(fn (): array => BasemapForm::toFormState($this->editableBasemap()))
                ->action(function (array $data): void {
                    $basemap = $this->editableBasemap();
                    $columns = BasemapForm::toColumns($data);

                    if ($basemap instanceof MapLayer) {
                        $basemap->update($columns);
                    } else {
                        // No basemap row yet (a fresh install, or one where the
                        // operator deleted it) — create rather than silently
                        // discarding the edit and leaving the map styleless.
                        MapLayer::query()->create([
                            'name'        => __('map.basemaps'),
                            'type'        => MapLayer::TYPE_BASEMAP,
                            'attribution' => null,
                            'enabled'     => true,
                            'order'       => 0,
                            ...$columns,
                        ]);
                    }

                    Notification::make()
                        ->success()
                        ->title(__('map.basemaps_saved'))
                        ->send();
                }),
            BasemapForm::fields(),
        );
    }

    /**
     * The basemap row this drawer edits — ANY basemap row, not only an enabled
     * one. The `activeBasemap` scope excludes disabled rows because that is the
     * right scope for RENDERING, but using it here meant a disabled basemap read
     * as "none exists", so saving created a SECOND row instead of editing the
     * first. Reachable in one click via the table's enabled toggle.
     */
    private function editableBasemap(): ?MapLayer
    {
        return MapLayer::query()
            ->where('type', MapLayer::TYPE_BASEMAP)
            ->orderBy('order')
            ->first();
    }
}
