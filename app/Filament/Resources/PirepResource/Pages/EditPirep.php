<?php

namespace App\Filament\Resources\PirepResource\Pages;

use App\Filament\Resources\PirepResource;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Services\PirepService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPirep extends EditRecord
{
    protected static string $resource = PirepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('accept')
                ->color('success')
                ->icon('heroicon-m-check-circle')
                ->label('Accept')
                ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::REJECTED))
                ->action(fn (Pirep $record) => app(PirepService::class)->changeState($record, PirepState::ACCEPTED)),

            Action::make('reject')
                ->color('danger')
                ->icon('heroicon-m-x-circle')
                ->label('Reject')
                ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::ACCEPTED))
                ->action(fn (Pirep $record) => app(PirepService::class)->changeState($record, PirepState::REJECTED)),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['distance'] = $data['distance']->toUnit('nmi');
        $data['planned_distance'] = $data['planned_distance']->toUnit('nmi');
        $data['block_fuel'] = $data['block_fuel']->toUnit('lbs');
        $data['fuel_used'] = $data['fuel_used']->toUnit('lbs');

        if ($data['flight_time'] !== null) {
            $data['flight_time'] = Carbon::createFromTime(
                (int) ($data['flight_time'] / 60),
                $data['flight_time'] % 60,
                0
            );
        }

        if ($data['planned_flight_time'] !== null) {
            $data['planned_flight_time'] = Carbon::createFromTime(
                (int) ($data['planned_flight_time'] / 60),
                $data['planned_flight_time'] % 60,
                0
            );
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['flight_time'] !== null) {
            $flt_time = Carbon::parse($data['flight_time']);
            $data['flight_time'] = $flt_time->hour * 60 + $flt_time->minute;
        }

        return $data;
    }
}
