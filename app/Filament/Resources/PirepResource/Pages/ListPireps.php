<?php

namespace App\Filament\Resources\PirepResource\Pages;

use App\Filament\Resources\PirepFieldResource;
use App\Filament\Resources\PirepResource;
use App\Models\Enums\PirepState;
use Filament\Actions\Action;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPireps extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PirepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pirepfields')->label('Pirep Fields')->icon('heroicon-o-clipboard-document-list')->url(PirepFieldResource::getUrl('index')),
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
