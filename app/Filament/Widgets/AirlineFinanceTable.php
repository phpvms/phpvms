<?php

namespace App\Filament\Widgets;

use App\Models\Airline;
use App\Models\JournalTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AirlineFinanceTable extends BaseWidget
{
    public int $airline_id;
    public string $start_date;
    public string $end_date;

    public function table(Table $table): Table
    {
        $airline = Airline::find($this->airline_id);

        return $table
            ->query(
                JournalTransaction::groupBy('transaction_group', 'currency')
                    ->selectRaw('transaction_group, 
                         currency, 
                         SUM(credit) as sum_credits, 
                         SUM(debit) as sum_debits')
                    ->where(['journal_id' => $airline->journal_id])
                    ->whereBetween('created_at', [$this->start_date, $this->end_date], 'AND')
                    ->orderBy('sum_credits', 'desc')
                    ->orderBy('sum_debits', 'desc')
                    ->orderBy('transaction_group', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('memo'),
            ]);
    }
}
