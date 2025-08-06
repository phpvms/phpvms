<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages\ManageExpenses;
use App\Models\Enums\ExpenseType;
use App\Models\Enums\FlightType;
use App\Models\Expense;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Expenses';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make('')->schema([
                    Select::make('airline_id')
                        ->relationship('airline', 'name')
                        ->searchable()
                        ->native(false)
                        ->label('Airline'),

                    Select::make('type')
                        ->options(ExpenseType::select())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->label('Expense Type'),

                    Select::make('flight_type')
                        ->options(FlightType::select())
                        ->searchable()
                        ->native(false)
                        ->multiple()
                        ->label('Flight Types'),
                ])->columns(3),

                Grid::make('')->schema([
                    TextInput::make('name')
                        ->required()
                        ->string()
                        ->label('Expense Name'),

                    TextInput::make('amount')
                        ->required()
                        ->numeric(),

                    Toggle::make('multiplier')
                        ->inline()
                        ->onColor('success')
                        ->onIcon('heroicon-m-check-circle')
                        ->offColor('danger')
                        ->offIcon('heroicon-m-x-circle'),

                    Toggle::make('active')
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
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ExpenseType::label($state)),

                TextColumn::make('amount')
                    ->sortable()
                    ->money(setting('units.currency')),

                TextColumn::make('airline.name')
                    ->sortable()
                    ->searchable(),

                IconColumn::make('active')
                    ->label('Active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Expense'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}
