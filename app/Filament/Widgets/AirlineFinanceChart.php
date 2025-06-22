<?php

namespace App\Filament\Widgets;

use App\Models\Airline;
use App\Models\JournalTransaction;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Colors\Color;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AirlineFinanceChart extends ChartWidget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Finance';

    protected static ?string $pollingInterval = null;

    protected function getData(): array
    {
        $start_date = $this->filters['start_date'] !== null ? Carbon::createFromTimeString($this->filters['start_date']) : now()->startOfYear();
        $end_date = $this->filters['end_date'] !== null ? Carbon::createFromTimeString($this->filters['end_date']) : now();
        $airline_id = $this->filters['airline_id'] ?? Auth::user()->airline_id;

        $airline = Airline::find($airline_id);

        $debit = Trend::query(JournalTransaction::where(['journal_id' => $airline->journal->id]))
            ->between(
                start: $start_date,
                end: $end_date
            )
            ->perMonth()
            ->sum('debit');

        $credit = Trend::query(JournalTransaction::where(['journal_id' => $airline->journal->id]))
            ->between(
                start: $start_date,
                end: $end_date
            )
            ->perMonth()
            ->sum('credit');

        return [
            'datasets' => [
                [
                    'label'           => 'Debit',
                    'data'            => $debit->map(fn (TrendValue $value): float => money($value->aggregate ?? 0, setting('units.currency'))->getValue()),
                    'backgroundColor' => 'rgba('.Color::Red[400].', 0.1)',
                    'borderColor'     => 'rgb('.Color::Red[400].')',
                ],
                [
                    'label'           => 'Credit',
                    'data'            => $credit->map(fn (TrendValue $value): float => money($value->aggregate ?? 0, setting('units.currency'))->getValue()),
                    'backgroundColor' => 'rgba('.Color::Green[400].', 0.1)',
                    'borderColor'     => 'rgb('.Color::Green[400].')',
                ],
            ],
            'labels' => $debit->map(fn (TrendValue $value): string => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public static function canView(): bool
    {
        return false;
    }
}
