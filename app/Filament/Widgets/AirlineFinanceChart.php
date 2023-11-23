<?php

namespace App\Filament\Widgets;

use App\Models\Airline;
use App\Models\JournalTransaction;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Colors\Color;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class AirlineFinanceChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Finance';
    protected static ?string $pollingInterval = null;

    public int $airline_id;
    public string $start_date;
    public string $end_date;

    #[On('updateFinanceFilters')]
    public function refresh(int $airline_id, string $start_date, string $end_date): void
    {
        $this->airline_id = $airline_id;
        $this->start_date = Carbon::createFromTimeString($start_date);
        $this->end_date = Carbon::createFromTimeString($end_date);
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $airline = Airline::find($this->airline_id);

        $debit = Trend::query(JournalTransaction::where(['journal_id' => $airline->journal->id]))
            ->between(
                start: Carbon::createFromTimeString($this->start_date),
                end: Carbon::createFromTimeString($this->end_date)
            )
            ->perMonth()
            ->sum('debit');

        $credit = Trend::query(JournalTransaction::where(['journal_id' => $airline->journal->id]))
            ->between(
                start: Carbon::createFromTimeString($this->start_date),
                end: Carbon::createFromTimeString($this->end_date)
            )
            ->perMonth()
            ->sum('credit');

        return [
            'datasets' => [
                [
                    'label'           => 'Debit',
                    'data'            => $debit->map(fn (TrendValue $value) => money($value->aggregate, setting('units.currency'))->getValue()),
                    'backgroundColor' => 'rgba('.Color::Red[400].', 0.1)',
                    'borderColor'     => 'rgb('.Color::Red[400].')',
                ],
                [
                    'label'           => 'Credit',
                    'data'            => $credit->map(fn (TrendValue $value) => money($value->aggregate, setting('units.currency'))->getValue()),
                    'backgroundColor' => 'rgba('.Color::Green[400].', 0.1)',
                    'borderColor'     => 'rgb('.Color::Green[400].')',
                ],
            ],
            'labels' => $debit->map(fn (TrendValue $value) => $value->date),
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
