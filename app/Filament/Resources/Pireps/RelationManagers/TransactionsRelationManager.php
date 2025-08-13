<?php

namespace App\Filament\Resources\Pireps\RelationManagers;

use App\Services\Finance\PirepFinanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                TextColumn::make('memo')
                    ->label(__('common.memo')),

                TextColumn::make('credit')
                    ->label(__('common.credit'))
                    ->color('success')
                    ->money(setting('units.currency'), 100)
                    ->summarize([
                        Sum::make()->money(setting('units.currency'), 100),
                    ]),

                TextColumn::make('debit')
                    ->label(__('common.debit'))
                    ->color('danger')
                    ->money(setting('units.currency'), 100)
                    ->summarize([
                        Sum::make()->money(setting('units.currency'), 100),
                    ]),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('recalculate_finances')
                    ->label(__('filament.recalculate_finances'))
                    ->action(function () {
                        app(PirepFinanceService::class)->processFinancesForPirep($this->getOwnerRecord());

                        Notification::make()
                            ->success()
                            ->title(__('filament.finances_recalculated'))
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

    public static function getModelLabel(): string
    {
        return trans_choice( 'pireps.transaction', 1);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans_choice('pireps.transaction', 2);
    }
}
