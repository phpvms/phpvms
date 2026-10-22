<?php

declare(strict_types=1);

namespace App\Filament\Resources\MapLayers\Tables;

use App\Models\MapLayer;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class MapLayersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('map.layer_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('map.layer_type'))
                    ->badge()
                    // Every type matched explicitly, and an unknown one shown
                    // raw rather than folded into a default: a `default =>
                    // raster` arm silently labelled the basemap row "Raster"
                    // when that type was added, which reads as a data bug
                    // rather than a missing case.
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        MapLayer::TYPE_RASTER  => __('map.layer_type_raster'),
                        MapLayer::TYPE_VECTOR  => __('map.layer_type_vector'),
                        MapLayer::TYPE_BASEMAP => __('map.layer_type_basemap'),
                        default                => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        MapLayer::TYPE_VECTOR  => 'info',
                        MapLayer::TYPE_BASEMAP => 'warning',
                        default                => 'gray',
                    }),

                TextColumn::make('attribution')
                    ->label(__('map.layer_attribution'))
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('min_zoom')
                    ->label(__('map.layer_min_zoom'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('max_zoom')
                    ->label(__('map.layer_max_zoom'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('opacity')
                    ->label(__('map.layer_opacity'))
                    ->numeric()
                    ->sortable(),

                // Self-saving, and therefore an UNGUARDED write by default:
                // `CanUpdateState::updateState` calls `$record->save()` with no
                // policy check of its own, so `view:map-layer` alone was enough
                // to disable an overlay — or the basemap row, stripping the
                // map's style site-wide. `disabled()` only hides the affordance;
                // `updateStateUsing` is the actual gate, because `updateState`
                // delegates to it instead of saving directly.
                ToggleColumn::make('enabled')
                    ->label(__('map.layer_enabled'))
                    ->disabled(fn (): bool => !Gate::allows('update', MapLayer::class))
                    ->updateStateUsing(function (MapLayer $record, mixed $state): void {
                        abort_unless(Gate::allows('update', MapLayer::class), 403);

                        $record->update(['enabled' => (bool) $state]);
                    }),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('map.layer_type'))
                    ->options([
                        MapLayer::TYPE_RASTER  => __('map.layer_type_raster'),
                        MapLayer::TYPE_VECTOR  => __('map.layer_type_vector'),
                        MapLayer::TYPE_BASEMAP => __('map.layer_type_basemap'),
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()->icon(Phosphor::TrashLight),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
