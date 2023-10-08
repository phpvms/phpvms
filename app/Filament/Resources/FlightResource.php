<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FlightResource\Pages;
use App\Models\Flight;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FlightResource extends Resource
{
    protected static ?string $model = Flight::class;

    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'flights';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ident')->label('Flight #'),
                TextColumn::make('dpt_airport_id')->label('Dep')->searchable(),
                TextColumn::make('arr_airport_id')->label('Arr')->searchable(),
                TextColumn::make('dpt_time')->label('Dpt Time'),
                TextColumn::make('arr_time')->label('Arr Time'),
                TextColumn::make('notes')->label('Notes'),
                IconColumn::make('active')->label('Active')->color(fn ($record) => $record->active ? 'success' : 'danger')->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                IconColumn::make('visible')->label('Visible')->color(fn ($record) => $record->visible ? 'success' : 'danger')->icon(fn ($record) => $record->visible ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->label('Add Flight'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFlights::route('/'),
            'create' => Pages\CreateFlight::route('/create'),
            'edit'   => Pages\EditFlight::route('/{record}/edit'),
        ];
    }
}
