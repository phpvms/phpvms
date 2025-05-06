<?php

namespace App\Filament\Resources\PirepResource\Pages;

use App\Filament\Resources\PirepResource;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Services\PirepService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPirep extends EditRecord
{
    protected static string $resource = PirepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('accept')
                ->color('success')
                ->icon('heroicon-m-check-circle')
                ->label('Accept')
                ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::REJECTED))
                ->action(fn (Pirep $record) => app(PirepService::class)->changeState($record, PirepState::ACCEPTED)),

            Actions\Action::make('reject')
                ->color('danger')
                ->icon('heroicon-m-x-circle')
                ->label('Reject')
                ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::ACCEPTED))
                ->action(fn (Pirep $record) => app(PirepService::class)->changeState($record, PirepState::REJECTED)),

            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['distance'] = $data['distance']->toUnit('nmi');
        $data['planned_distance'] = $data['planned_distance']->toUnit('nmi');
        $data['block_fuel'] = $data['block_fuel']->toUnit('lbs');
        $data['fuel_used'] = $data['fuel_used']->toUnit('lbs');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['flight_time'] = $data['hours'] * 60 + $data['minutes'];

        return $data;
    }
}
