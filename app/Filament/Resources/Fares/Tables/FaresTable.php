<?php

namespace App\Filament\Resources\Fares\Tables;

use App\Enums\FareType;
use App\Filament\Resources\Fares\Support\FareTrace;
use App\Models\Fare;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class FaresTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['subfleets', 'flights']))
            ->columns([
                TextColumn::make('code')
                    ->label(__('flights.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('common.type'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('price')
                    ->label(__('common.price'))
                    ->money(setting('units.currency'))
                    ->sortable(),

                TextColumn::make('cost')
                    ->label(__('common.cost'))
                    ->money(setting('units.currency'))
                    ->sortable(),

                TextColumn::make('overrides')
                    ->label(__('filament.fare_overrides'))
                    ->badge()
                    ->color('gray')
                    ->state(function (Fare $record): ?string {
                        $subfleets = $record->subfleets->filter(fn ($subfleet): bool => FareTrace::pivotOverrides($subfleet->pivot))->count();
                        $flights = $record->flights->filter(fn ($flight): bool => FareTrace::pivotOverrides($flight->pivot))->count();

                        if ($subfleets === 0 && $flights === 0) {
                            return null;
                        }

                        $parts = [];
                        if ($subfleets > 0) {
                            $parts[] = $subfleets.' '.mb_strtolower(trans_choice('common.subfleet', $subfleets));
                        }

                        if ($flights > 0) {
                            $parts[] = $flights.' '.mb_strtolower(trans_choice('common.flight', $flights));
                        }

                        return implode(' · ', $parts);
                    }),

                TextColumn::make('notes')
                    ->label(__('common.notes')),

                IconColumn::make('active')
                    ->label(__('common.active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters(filters: [
                SelectFilter::make('type')
                    ->label(__('common.type'))
                    ->options(FareType::class),

                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('overrides')
                    ->label(__('filament.fare_overrides'))
                    ->icon(TablerIcon::Sitemap)
                    ->color('gray')
                    ->modalHeading(fn (Fare $record): string => $record->code.' · '.$record->name)
                    ->modalDescription(__('filament.fare_map_description'))
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('common.close'))
                    ->modalContent(fn (Fare $record): Factory|View => view('filament.fares.override-map', [
                        'fare' => $record->loadMissing(['subfleets', 'flights']),
                    ])),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
            ]);
    }
}
