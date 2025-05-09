<?php

namespace App\Filament\Widgets;

use App\Models\Airline;
use App\Models\JournalTransaction;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AirlineFinanceTable extends TableWidget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = null;

    public function getTableRecordKey(Model $record): string
    {
        return $record->transaction_group;
    }

    public function table(Table $table): Table
    {
        $start_date = $this->filters['start_date'] !== null ? Carbon::createFromTimeString($this->filters['start_date']) : now()->startOfYear();
        $end_date = $this->filters['end_date'] !== null ? Carbon::createFromTimeString($this->filters['end_date']) : now();
        $airline_id = $this->filters['airline_id'] ?? Auth::user()->airline_id;
        $airline_journal_id = Airline::find($airline_id)->journal->id;

        return $table
            ->query(
                JournalTransaction::query()
                    ->selectRaw('transaction_group, 
                     currency, 
                     SUM(credit) as sum_credits, 
                     SUM(debit) as sum_debits')
                    ->where(['journal_id' => $airline_journal_id])
                    ->whereBetween('created_at', [$start_date, $end_date], 'AND')
                    ->groupBy('transaction_group', 'currency')
                    ->orderBy('sum_credits', 'desc')
                    ->orderBy('sum_debits', 'desc')
                    ->orderBy('transaction_group', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('transaction_group')
                    ->label('Expense'),

                Tables\Columns\TextColumn::make('sum_credits')
                    ->label('Credit')
                    ->formatStateUsing(fn (JournalTransaction $record): string => money($record->sum_credits ?? 0, $record->currency))
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->money(setting('units.currency'), divideBy: 100)
                    ),

                Tables\Columns\TextColumn::make('sum_debits')
                    ->label('Debit')
                    ->formatStateUsing(fn (JournalTransaction $record): string => money($record->sum_debits ?? 0, $record->currency))
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->money(setting('units.currency'), divideBy: 100)
                    ),
            ]);
    }

    public static function canView(): bool
    {
        return false;
    }
}
