<?php

namespace App\Filament\Resources\Pireps\Pages;

use App\Filament\Concerns\PutsPrimaryActionLast;
use App\Filament\Resources\Pireps\Actions\AcceptAction;
use App\Filament\Resources\Pireps\Actions\RejectAction;
use App\Filament\Resources\Pireps\PirepResource;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditPirep extends EditRecord
{
    use PutsPrimaryActionLast;

    protected static string $resource = PirepResource::class;

    #[Override]
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

    #[Override]
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

    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['flight_time'] !== null) {
            $flt_time = Carbon::parse($data['flight_time']);
            $data['flight_time'] = $flt_time->hour * 60 + $flt_time->minute;
        }

        return $data;
    }
}
