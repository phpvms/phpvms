<?php

namespace App\Filament\Resources\PirepResource\RelationManagers;

use App\Services\Finance\PirepFinanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('memo')
            ->columns([
                TextColumn::make('memo'),
                TextColumn::make('credit')->color('success')->money(setting('units.currency'))->summarize([
                    Sum::make(),
                ]),
                TextColumn::make('debit')->color('danger')->money(setting('units.currency'))->summarize([
                    Sum::make(),
                ]),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('recalculate_finances')->action(function () {
                    app(PirepFinanceService::class)->processFinancesForPirep($this->getOwnerRecord());

                    Notification::make('')
                        ->success()
                        ->title('Finances Recalculated')
                        ->send();
                }),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
