<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Finances;
use App\Models\Airline;
use App\Models\JournalTransaction;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
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

    public function getTableRecordKey(Model|array $record): string
    {
        return $record->transaction_group;
    }

    public function table(Table $table): Table
    {
        $filters = $this->pageFilters ?? [
            'start_date' => null,
            'end_date'   => null,
            'airline_id' => null,
        ];

        $start_date = $filters['start_date'] !== null ? Carbon::createFromTimeString($filters['start_date']) : now()->startOfYear();
        $end_date = $filters['end_date'] !== null ? Carbon::createFromTimeString($filters['end_date']) : now();
        $airline_id = $filters['airline_id'];

        if ($airline_id === null || $airline_id === '') {
            $airline_id = Auth::user()->airline_id;
        }

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
                TextColumn::make('transaction_group')
                    ->label('Expense'),

                TextColumn::make('sum_credits')
                    ->label('Credit')
                    ->color('success')
                    ->formatStateUsing(fn (JournalTransaction $record): string => money($record->sum_credits ?? 0, $record->currency))
                    ->summarize(
                        Sum::make()
                            ->money(setting('units.currency'), divideBy: 100)
                    ),

                TextColumn::make('sum_debits')
                    ->label('Debit')
                    ->color('danger')
                    ->formatStateUsing(fn (JournalTransaction $record): string => money($record->sum_debits ?? 0, $record->currency))
                    ->summarize(
                        Sum::make()
                            ->money(setting('units.currency'), divideBy: 100)
                    ),
            ]);
    }

    public static function canView(): bool
    {
        // Display if the page is finance or /livewire/update from finance
        return request()->url() === Finances::getUrl() || (request()->url() !== Dashboard::getUrl() && str(request()->header('referer'))->contains(Finances::getUrl()));
    }
}
