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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('common.name'))
                    ->string()
                    ->required(),

                TextInput::make('amount')
                    ->label(__('common.amount'))
                    ->numeric()
                    ->step(0.01)
                    ->required(),

                Select::make('type')
                    ->label(__('common.type'))
                    ->options(ExpenseType::select())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name')),

                TextColumn::make('amount')
                    ->label(__('common.amount'))
                    ->money(setting('units.currency')),

                TextColumn::make('type')
                    ->label(__('common.type'))
                    ->formatStateUsing(fn (string $state): string => ExpenseType::label($state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon(Heroicon::OutlinedPlusCircle)
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
                    ->icon(Heroicon::OutlinedPlusCircle)
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

    public static function getModelLabel(): string
    {
        return __('expenses.expense');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('expenses.expense'))
            ->plural()
            ->toString();
    }
}
