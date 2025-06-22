<?php

namespace App\Filament\Resources\PirepResource\RelationManagers;

use App\Services\Finance\PirepFinanceService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('memo')
            ->columns([
                Tables\Columns\TextColumn::make('memo'),
                Tables\Columns\TextColumn::make('credit')->color('success')->money(setting('units.currency'))->summarize([
                    Tables\Columns\Summarizers\Sum::make(),
                ]),
                Tables\Columns\TextColumn::make('debit')->color('danger')->money(setting('units.currency'))->summarize([
                    Tables\Columns\Summarizers\Sum::make(),
                ]),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('recalculate_finances')->action(function (): void {
                    app(PirepFinanceService::class)->processFinancesForPirep($this->getOwnerRecord());

                    Notification::make('')
                        ->success()
                        ->title('Finances Recalculated')
                        ->send();
                }),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
