<?php

namespace App\Filament\Resources\PirepResource\Widgets;

use App\Filament\Resources\PirepResource\Pages\ListPireps;
use App\Models\Enums\PirepState;
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
        return [
            Stat::make('Pireps', $this->getPageTableQuery()->count()),
            Stat::make('Accepted Pireps', $this->getPageTableQuery()->where('state', PirepState::ACCEPTED)->count()),
            Stat::make('Pending Pireps', $this->getPageTableQuery()->where('state', PirepState::PENDING)->count()),
        ];
    }
}
