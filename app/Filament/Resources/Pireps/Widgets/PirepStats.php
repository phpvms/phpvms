<?php

namespace App\Filament\Resources\Pireps\Widgets;

use App\Filament\Resources\Pireps\Pages\ListPireps;
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

    protected ?string $pollingInterval = null;

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
            Stat::make(trans_choice('common.pirep', 2), $this->getPageTableQuery()->count())->chart($pirepData->map(fn (TrendValue $value) => $value->aggregate)->toArray()),
            Stat::make(__('pireps.state.accepted'), $this->getPageTableQuery()->where('state', PirepState::ACCEPTED)->count())->color('danger'),
            Stat::make(__('pireps.state.pending'), $this->getPageTableQuery()->where('state', PirepState::PENDING)->count()),
        ];
    }
}
