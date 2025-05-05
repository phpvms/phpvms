<?php

namespace App\Filament\RelationManagers;

use App\Models\Aircraft;
use App\Models\Enums\ExpenseType;
use App\Models\Subfleet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->string()
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->step(0.01)
                    ->required(),

                Forms\Components\Select::make('type')
                    ->options(ExpenseType::select())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('amount')->money(setting('units.currency')),
                Tables\Columns\TextColumn::make('type')->formatStateUsing(fn (string $state): string => ExpenseType::label($state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Add Expense')->icon('heroicon-o-plus-circle')
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        $ownerRecord = $livewire->getOwnerRecord();
                        if ($ownerRecord instanceof Subfleet) {
                            $data['airline_id'] = $ownerRecord->airline_id;
                        } elseif ($ownerRecord instanceof Aircraft) {
                            $data['airline_id'] = $ownerRecord->subfleet->airline_id;
                        }

                        return $data;
                    }),
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
                    ->label('Add Expense')
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
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
