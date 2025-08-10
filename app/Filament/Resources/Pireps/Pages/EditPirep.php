<?php

namespace App\Filament\Resources\Pireps\Pages;

use App\Filament\Resources\Pireps\Actions\AcceptAction;
use App\Filament\Resources\Pireps\Actions\RejectAction;
use App\Filament\Resources\Pireps\PirepResource;
use Carbon\Carbon;
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
            AcceptAction::make(),
            RejectAction::make(),
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
