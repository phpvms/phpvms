<?php

namespace App\Filament\Resources\PirepResource\Pages;

use App\Filament\Resources\PirepResource;
use App\Models\Enums\PirepState;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPireps extends ListRecords
{
    protected static string $resource = PirepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Pirep Fields')->url(fn (): string => 'pirepfields'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PirepResource\Widgets\PirepStats::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all'      => Tab::make(),
            'pending'  => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('state', PirepState::PENDING)),
            'rejected' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('state', PirepState::REJECTED)),
            'accepted' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('state', PirepState::ACCEPTED)),
        ];
    }
}
