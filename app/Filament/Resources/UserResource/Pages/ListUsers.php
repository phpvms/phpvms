<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserFieldResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Widgets\UserStats;
use App\Models\Enums\UserState;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('userfields')->label('User Fields')->url(UserFieldResource::getUrl('index')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            //UserStats::class
        ];
    }

    public function getTabs(): array
    {
        return [
            'all'       => Tab::make(),
            'pending'   => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('state', UserState::PENDING)),
            'active'    => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('state', UserState::ACTIVE)),
            'rejected'  => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('state', UserState::REJECTED)),
            'on_leave'  => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('state', UserState::ON_LEAVE)),
            'suspended' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('state', UserState::SUSPENDED)),
            'deleted'   => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('state', UserState::DELETED)),
        ];
    }
}
