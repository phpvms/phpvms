<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Enums\ExpenseType;
use App\Models\Enums\FlightType;
use App\Models\Expense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationGroup = 'Config';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make('')->schema([
                    Forms\Components\Select::make('airline_id')
                        ->relationship('airline', 'name')
                        ->searchable()
                        ->native(false)
                        ->label('Airline'),

                    Forms\Components\Select::make('type')
                        ->options(ExpenseType::select())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->label('Expense Type'),

                    Forms\Components\Select::make('flight_type')
                        ->options(FlightType::select())
                        ->searchable()
                        ->native(false)
                        ->multiple()
                        ->label('Flight Types'),
                ])->columns(3),

                Forms\Components\Grid::make('')->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->string()
                        ->label('Expense Name'),

                    Forms\Components\TextInput::make('amount')
                        ->required()
                        ->numeric(),

                    Forms\Components\Toggle::make('multiplier')
                        ->inline()
                        ->onColor('success')
                        ->onIcon('heroicon-m-check-circle')
                        ->offColor('danger')
                        ->offIcon('heroicon-m-x-circle'),

                    Forms\Components\Toggle::make('active')
                        ->inline()
                        ->onColor('success')
                        ->onIcon('heroicon-m-check-circle')
                        ->offColor('danger')
                        ->offIcon('heroicon-m-x-circle'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => ExpenseType::label($state)),

                Tables\Columns\TextColumn::make('amount')
                    ->sortable()
                    ->money(setting('units.currency')),

                Tables\Columns\TextColumn::make('airline.name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
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
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Expense'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageExpenses::route('/'),
        ];
    }
}
