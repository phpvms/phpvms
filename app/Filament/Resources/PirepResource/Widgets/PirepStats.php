<?php

namespace App\Filament\Resources\PirepResource\Widgets;

use App\Filament\Resources\PirepResource\Pages\ListPireps;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class PirepStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected static ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListPireps::class;
    }

    protected function getStats(): array
    {
        $pirepData = Trend::model(Pirep::class)
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->count();

        return [
            Stat::make('Pireps', $this->getPageTableQuery()->count())->chart($pirepData->map(fn (TrendValue $value) => $value->aggregate)->toArray()),
            Stat::make('Accepted Pireps', $this->getPageTableQuery()->where('state', PirepState::ACCEPTED)->count())->color('danger'),
            Stat::make('Pending Pireps', $this->getPageTableQuery()->where('state', PirepState::PENDING)->count()),
        ];
    }
}
