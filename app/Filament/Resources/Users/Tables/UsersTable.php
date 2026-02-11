<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Airport;
use App\Models\Enums\UserState;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ident')
                    ->label('ID')
                    ->searchable(['pilot_id'])
                    ->sortable(),

                TextColumn::make('callsign')
                    ->toggleable()
                    ->label(__('flights.callsign'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->toggleable()
                    ->label(__('common.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->toggleable()
                    ->label(__('common.registered_on'))
                    ->dateTime('d/m/Y')
                    ->sortable(),

                TextColumn::make('home_airport_id')
                    ->label(__('airports.home'))
                    ->toggleable()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('state')
                    ->label(__('common.state'))
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        UserState::PENDING => 'warning',
                        UserState::ACTIVE  => 'success',
                        default            => 'info',
                    })
                    ->formatStateUsing(fn (int $state): string => UserState::label($state))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('home_airport_id')
                    ->label(__('airports.home'))
                    ->relationship('home_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('curr_airport_id')
                    ->label(__('airports.current'))
                    ->relationship('current_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('state')
                    ->label(__('common.state'))
                    ->options(UserState::labels()),
            ])
            ->recordActions([
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
