<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Finances;
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

    protected ?string $heading = 'Finance';

    protected ?string $pollingInterval = null;

    protected function getData(): array
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
                    'data'            => $debit->map(fn (TrendValue $value) => money($value->aggregate ?? 0, setting('units.currency'))->getValue()),
                    'backgroundColor' => str(Color::Red[600])->replace(')', '/0.1)'),
                    'borderColor'     => Color::Red[600],
                ],
                [
                    'label'           => 'Credit',
                    'data'            => $credit->map(fn (TrendValue $value) => money($value->aggregate ?? 0, setting('units.currency'))->getValue()),
                    'backgroundColor' => str(Color::Green[600])->replace(')', '/0.1)'),
                    'borderColor'     => Color::Green[600],
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
        // Display if the page is finance or /livewire/update from finance
        return request()->url() === Finances::getUrl() || (request()->url() !== Dashboard::getUrl() && str(request()->header('referer'))->contains(Finances::getUrl()));
    }
}
