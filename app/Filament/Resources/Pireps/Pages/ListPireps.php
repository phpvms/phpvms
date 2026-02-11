<?php

namespace App\Filament\Resources\Pireps\Pages;

use App\Filament\Resources\Pireps\Actions\PirepFieldsAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Filament\Resources\Pireps\Widgets\PirepStats;
use App\Models\Enums\PirepState;
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
            PirepFieldsAction::make(),
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
            'all'      => Tab::make()->label(__('filament-tables::table.filters.multi_select.placeholder')),
            'pending'  => Tab::make()->label(PirepState::label(PirepState::PENDING))->modifyQueryUsing(fn (Builder $query) => $query->where('state', PirepState::PENDING)),
            'rejected' => Tab::make()->label(PirepState::label(PirepState::REJECTED))->modifyQueryUsing(fn (Builder $query) => $query->where('state', PirepState::REJECTED)),
            'accepted' => Tab::make()->label(PirepState::label(PirepState::ACCEPTED))->modifyQueryUsing(fn (Builder $query) => $query->where('state', PirepState::ACCEPTED)),
        ];
    }
}
