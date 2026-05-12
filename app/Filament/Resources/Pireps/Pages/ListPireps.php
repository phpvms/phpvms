<?php

namespace App\Filament\Resources\Pireps\Pages;

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Actions\PirepFieldsAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Filament\Resources\Pireps\Widgets\PirepStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPireps extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PirepResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            PirepFieldsAction::make(),
        ];
    }

    #[\Override]
    protected function getHeaderWidgets(): array
    {
        return [
            PirepStats::class,
        ];
    }

    #[\Override]
    public function getTabs(): array
    {
        return [
            'all'      => Tab::make()->label(__('filament-tables::table.filters.multi_select.placeholder')),
            'pending'  => Tab::make()->label(PirepState::PENDING->getLabel())->modifyQueryUsing(fn (Builder $query) => $query->where('state', PirepState::PENDING)),
            'rejected' => Tab::make()->label(PirepState::REJECTED->getLabel())->modifyQueryUsing(fn (Builder $query) => $query->where('state', PirepState::REJECTED)),
            'accepted' => Tab::make()->label(PirepState::ACCEPTED->getLabel())->modifyQueryUsing(fn (Builder $query) => $query->where('state', PirepState::ACCEPTED)),
        ];
    }
}
