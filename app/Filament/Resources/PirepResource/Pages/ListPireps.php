<?php

namespace App\Filament\Resources\PirepResource\Pages;

use App\Filament\Resources\PirepFields\PirepFieldResource;
use App\Filament\Resources\PirepResource;
use App\Filament\Resources\PirepResource\Widgets\PirepStats;
use App\Models\Enums\PirepState;
use Filament\Actions\Action;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPireps extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PirepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pirepfields')
                ->label('Pirep Fields')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(PirepFieldResource::getUrl('index'))
                ->visible(fn (): bool => auth()->user()?->can('view_any_pirep::field')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PirepStats::class,
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
