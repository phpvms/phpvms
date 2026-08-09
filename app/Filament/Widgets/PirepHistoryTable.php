<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports\FlightsReport;
use App\Models\Pirep;
use App\Support\Units\Time;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Override;

/**
 * PIREP history report — every filed PIREP in the selected period, with
 * hours and landing performance. Driven by the Reports page filters.
 */
class PirepHistoryTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

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

        return $table
            ->query(
                Pirep::query()
                    ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED])
                    ->whereBetween('submitted_at', [$start_date, $end_date])
                    ->when(
                        filled($airline_id),
                        fn (Builder $query): Builder => $query->where('airline_id', $airline_id),
                    )
                    ->with(['airline', 'user', 'dpt_airport:id,icao,name', 'arr_airport:id,icao,name'])
            )
            ->heading(__('filament.reports_pireps_heading'))
            ->columns([
                TextColumn::make('flight_number')
                    ->label(__('flights.flightnumber'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label(trans_choice('common.pilot', 1))
                    ->sortable(),

                TextColumn::make('airline.name')
                    ->label(__('common.airline'))
                    ->sortable(),

                TextColumn::make('route')
                    ->label(__('flights.route'))
                    ->state(fn (Pirep $record): string => trim($record->dpt_airport_id.' → '.$record->arr_airport_id)),

                TextColumn::make('flight_time')
                    ->label(__('pireps.flight_time'))
                    ->formatStateUsing(fn (?int $state): string => Time::minutesToTimeString($state ?? 0)),

                TextColumn::make('landing_rate')
                    ->label(__('pireps.landing_rate'))
                    ->formatStateUsing(fn (int|float|null $state): string => $state !== null && (int) $state !== 0 ? number_format((float) $state).' fpm' : '—')
                    ->color(fn (int|float|null $state): string => match (true) {
                        $state === null || (int) $state === 0 => 'gray',
                        $state > 0                            => 'danger',
                        default                               => 'success',
                    }),

                TextColumn::make('state')
                    ->label(__('common.state'))
                    ->badge(),

                TextColumn::make('submitted_at')
                    ->label(__('pireps.submitted'))
                    ->sortable()
                    ->dateTime(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    #[Override]
    public static function canView(): bool
    {
        // Only render on the Flights report page (or a Livewire update request from it)
        if (request()->url() === FlightsReport::getUrl()) {
            return true;
        }

        return request()->url() !== Dashboard::getUrl() && str(request()->header('referer'))->contains(FlightsReport::getUrl());
    }
}
