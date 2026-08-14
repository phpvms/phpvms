<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\FlightBundles\Resources\Flight\FlightResource;
use App\Filament\Resources\FlightBundles\Resources\Flight\Schemas\FlightForm;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateFlight extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = FlightResource::class;

    private bool $isCreatingReturnFlight = false;

    #[Override]
    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();
        array_splice($actions, 1, 0, [$this->getCreateReturnFlightFormAction()]);

        return $this->reversePrimaryButtons($actions);
    }

    public function createReturnFlight(): void
    {
        $this->isCreatingReturnFlight = true;

        try {
            $this->create(another: true);
        } finally {
            $this->isCreatingReturnFlight = false;
        }
    }

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // flight_time is not on the form any more; only convert it when a
        // caller actually supplied one.
        if (isset($data['flight_time'])) {
            $flt_time = Carbon::parse($data['flight_time']);
            $data['flight_time'] = $flt_time->hour * 60 + $flt_time->minute;
        }

        return $data;
    }

    #[Override]
    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        if (!$this->isCreatingReturnFlight) {
            return parent::preserveFormDataWhenCreatingAnother($data);
        }

        $departureAirport = $data['dpt_airport_id'] ?? null;
        $data['dpt_airport_id'] = $data['arr_airport_id'] ?? null;
        $data['arr_airport_id'] = $departureAirport;
        $data['route'] = null;

        return [...$data, ...FlightForm::routeCalculations($data)];
    }

    private function getCreateReturnFlightFormAction(): Action
    {
        return Action::make('createReturnFlight')
            ->label(__('flights.create_return_flight'))
            ->action('createReturnFlight')
            ->color('gray');
    }
}
