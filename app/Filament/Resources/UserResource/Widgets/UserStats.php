<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Enums\UserState;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected static ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListUsers::class;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Users', $this->getPageTableQuery()->count()),
            Stat::make('Active Users', $this->getPageTableQuery()->where('state', UserState::ACTIVE)->count()),
            Stat::make('Pending Users', $this->getPageTableQuery()->where('state', UserState::PENDING)->count()),
        ];
    }
}
