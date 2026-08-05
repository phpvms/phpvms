<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Finances;
use App\Models\Airline;
use App\Models\JournalTransaction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Override;

class AirlineFinanceTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = null;

    /**
     * Grouped rows share a `transaction_group` across currencies, so the plain
     * group name is not unique. Composite key keeps every row addressable.
     *
     * @param JournalTransaction $record
     */
    #[Override]
    public function getTableRecordKey(Model|array $record): string
    {
        return $record->transaction_group.'|'.$record->currency;
    }

    #[Override]
    public function table(Table $table): Table
    {
        $filters = $this->pageFilters ?? [
            'start_date' => null,
            'end_date'   => null,
            'airline_id' => null,
        ];

        $start_date = $filters['start_date'] !== null ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfYear();
        $end_date = $filters['end_date'] !== null ? Carbon::parse($filters['end_date'])->endOfDay() : now();
        $airline_id = $filters['airline_id'];

        if ($airline_id === null || $airline_id === '') {
            $airline_id = Auth::user()->airline_id;
        }

        $airline = Airline::find($airline_id);

        $query = JournalTransaction::query()
            ->selectRaw('transaction_group, 
             currency, 
             SUM(credit) as sum_credits, 
             SUM(debit) as sum_debits')
            ->whereBetween('created_at', [$start_date, $end_date], 'AND')
            ->groupBy('transaction_group', 'currency')
            ->orderBy('sum_credits', 'desc')
            ->orderBy('sum_debits', 'desc')
            ->orderBy('transaction_group', 'asc');

        if ($airline?->journal) {
            $query->where(['journal_id' => $airline->journal->id]);
        } else {
            // No airline selected (or no journal yet) — show an empty table.
            $query->whereRaw('1 = 0');
        }

        return $table
            // Grouped query — don't let Filament append a default ORDER BY on
            // the primary key, it violates GROUP BY on pgsql.
            ->defaultKeySort(false)
            ->query($query)
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

    #[Override]
    public static function canView(): bool
    {
        // Display if the page is finance, or a /livewire-{hash}/update request
        // coming from it
        if (request()->url() === Finances::getUrl()) {
            return true;
        }

        return request()->url() !== Dashboard::getUrl() && str(request()->header('referer'))->contains(Finances::getUrl());
    }
}
