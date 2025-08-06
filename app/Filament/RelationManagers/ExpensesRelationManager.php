<?php

namespace App\Filament\RelationManagers;

use App\Models\Aircraft;
use App\Models\Enums\ExpenseType;
use App\Models\Subfleet;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->string()
                    ->required(),

                TextInput::make('amount')
                    ->numeric()
                    ->step(0.01)
                    ->required(),

                Select::make('type')
                    ->options(ExpenseType::select())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('amount')->money(setting('units.currency')),
                TextColumn::make('type')->formatStateUsing(fn (string $state): string => ExpenseType::label($state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->label('Add Expense')->icon('heroicon-o-plus-circle')
                    ->mutateDataUsing(function (array $data, RelationManager $livewire): array {
                        $ownerRecord = $livewire->getOwnerRecord();
                        if ($ownerRecord instanceof Subfleet) {
                            $data['airline_id'] = $ownerRecord->airline_id;
                        } elseif ($ownerRecord instanceof Aircraft) {
                            $data['airline_id'] = $ownerRecord->subfleet->airline_id;
                        }

                        return $data;
                    }),
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
                    ->label('Add Expense')
                    ->mutateDataUsing(function (array $data, RelationManager $livewire): array {
                        $ownerRecord = $livewire->getOwnerRecord();
                        if ($ownerRecord instanceof Subfleet) {
                            $data['airline_id'] = $ownerRecord->airline_id;
                        } elseif ($ownerRecord instanceof Aircraft) {
                            $data['airline_id'] = $ownerRecord->subfleet->airline_id;
                        }

                        return $data;
                    }),
            ]);
    }
}
