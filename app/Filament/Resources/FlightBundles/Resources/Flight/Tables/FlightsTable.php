<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight\Tables;

use App\Jobs\RecomputeBundleVisibility;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FlightsTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('airline'))
            ->columns([
                TextColumn::make('ident')
                    ->label(trans_choice('common.flight', 1).' #')
                    ->searchable(['flight_number'])
                    ->sortable(['airline_id', 'flight_number']),

                TextColumn::make('dpt_airport_id')
                    ->label(__('flights.dep'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('arr_airport_id')
                    ->label(__('flights.arr'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('dpt_time')
                    ->label(__('flights.departuretime'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('arr_time')
                    ->label(__('flights.arrivaltime'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('notes')
                    ->label(__('common.notes'))
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('enabled')
                    ->label(__('common.enabled'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('status_badge')
                    ->label(__('common.status'))
                    ->badge()
                    ->state(function (Flight $record): string {
                        if (!$record->enabled) {
                            return __('filament.flights.status.disabled');
                        }

                        if ($record->visible) {
                            return __('filament.flights.status.enabled_in_window');
                        }

                        return __('filament.flights.status.enabled_out_of_window');
                    })
                    ->color(function (Flight $record): string {
                        if (!$record->enabled) {
                            return 'danger';
                        }

                        return $record->visible ? 'success' : 'warning';
                    }),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('airline')
                    ->relationship('airline', 'name')
                    ->label(__('common.airline'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('dpt_airport')
                    ->label(__('airports.departure'))
                    ->relationship('dpt_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('arr_airport')
                    ->label(__('airports.arrival'))
                    ->relationship('arr_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enable')
                        ->label(__('common.enable'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            self::bulkSetEnabled($records, true);
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('disable')
                        ->label(__('common.disable'))
                        ->icon(Heroicon::OutlinedMinusCircle)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            self::bulkSetEnabled($records, false);
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('move_to_bundle')
                        ->label(__('filament.flights.bulk_actions.move_to_bundle'))
                        ->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->schema([
                            Select::make('bundle_id')
                                ->label(__('filament.flights.fields.bundle'))
                                ->options(fn (): array => FlightBundle::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required()
                                ->exists('flight_bundles', 'id'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            self::bulkMoveToBundle($records, (int) $data['bundle_id']);
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('attach_subfleets')
                        ->label(__('filament.flights.bulk_actions.attach_subfleets'))
                        ->icon(Heroicon::OutlinedLink)
                        ->schema([
                            Select::make('subfleet_ids')
                                ->label(__('filament.flights.bulk_actions.subfleets_field'))
                                ->multiple()
                                ->options(fn (): array => self::subfleetSelectOptions())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            self::bulkSyncSubfleets($records, $data['subfleet_ids'], attach: true);
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('detach_subfleets')
                        ->label(__('filament.flights.bulk_actions.detach_subfleets'))
                        ->icon(Heroicon::OutlinedLinkSlash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->schema([
                            Select::make('subfleet_ids')
                                ->label(__('filament.flights.bulk_actions.subfleets_field'))
                                ->multiple()
                                ->options(fn (): array => self::subfleetSelectOptions())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            self::bulkSyncSubfleets($records, $data['subfleet_ids'], attach: false);
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
            ]);
    }

    /**
     * Bulk update `flights.enabled` and dispatch a visibility recompute per
     * affected bundle so the cron-managed `visible` column refreshes without
     * waiting for the nightly run.
     *
     * @param Collection<int, Flight> $records
     */
    protected static function bulkSetEnabled(Collection $records, bool $enabled): void
    {
        $ids = $records->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        Flight::query()->whereIn('id', $ids)->update(['enabled' => $enabled]);

        $records->pluck('bundle_id')
            ->filter()
            ->unique()
            ->each(fn ($bundleId): mixed => RecomputeBundleVisibility::dispatch((int) $bundleId));

        Notification::make()
            ->title(__($enabled
                ? 'filament.flights.notifications.enabled'
                : 'filament.flights.notifications.disabled', ['count' => count($ids)]))
            ->success()
            ->send();
    }

    /**
     * Reassign every selected flight to the chosen bundle and recompute
     * visibility for both the originating bundles and the destination bundle.
     *
     * @param Collection<int, Flight> $records
     */
    protected static function bulkMoveToBundle(Collection $records, int $bundleId): void
    {
        $ids = $records->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        $bundle = FlightBundle::query()->find($bundleId);

        if (!$bundle instanceof FlightBundle) {
            return;
        }

        $oldBundleIds = $records->pluck('bundle_id')->filter()->unique()->all();

        Flight::query()->whereIn('id', $ids)->update(['bundle_id' => $bundleId]);

        collect($oldBundleIds)
            ->push($bundleId)
            ->unique()
            ->each(fn ($id): mixed => RecomputeBundleVisibility::dispatch((int) $id));

        Notification::make()
            ->title(__('filament.flights.notifications.moved', [
                'count'  => count($ids),
                'bundle' => $bundle->name,
            ]))
            ->success()
            ->send();
    }

    /**
     * Attach or detach the given subfleet ids on every selected flight via the
     * `flight_subfleet` pivot. Attach uses `syncWithoutDetaching` to stay
     * idempotent; detach is a straight `detach`.
     *
     * @param Collection<int, Flight> $records
     * @param array<int, int|string>  $subfleetIds
     */
    protected static function bulkSyncSubfleets(Collection $records, array $subfleetIds, bool $attach): void
    {
        if ($subfleetIds === []) {
            return;
        }

        $ids = collect($subfleetIds)->map(fn ($id): int => (int) $id)->all();

        $records->each(function (Flight $flight) use ($ids, $attach): void {
            if ($attach) {
                $flight->subfleets()->syncWithoutDetaching($ids);
            } else {
                $flight->subfleets()->detach($ids);
            }
        });

        Notification::make()
            ->title(__($attach
                ? 'filament.flights.notifications.attached'
                : 'filament.flights.notifications.detached', [
                    'count'     => $records->count(),
                    'subfleets' => count($ids),
                ]))
            ->success()
            ->send();
    }

    /**
     * Build the {id => "Airline - Name"} option list used by the attach/detach
     * subfleet bulk actions. Eager-loads `airline` to avoid N+1.
     *
     * @return array<int, string>
     */
    protected static function subfleetSelectOptions(): array
    {
        return Subfleet::query()
            ->with('airline:id,name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Subfleet $subfleet): array => [
                $subfleet->id => trim(($subfleet->airline?->name ? $subfleet->airline->name.' - ' : '').$subfleet->name),
            ])
            ->all();
    }
}
