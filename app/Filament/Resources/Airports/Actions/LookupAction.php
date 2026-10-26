<?php

namespace App\Filament\Resources\Airports\Actions;

use App\Services\AirportService;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class LookupAction
{
    public static function make(): Action
    {
        return Action::make('lookup')
            ->label(__('airports.lookup'))
            ->icon(Phosphor::MagnifyingGlassLight)
            ->extraAttributes(['id' => 'airport-icao-lookup'])
            ->action(function (Get $get, Set $set): void {
                $airport = app(AirportService::class)->lookupAirport($get('icao'));

                foreach ($airport as $key => $value) {
                    // The lookup carries the catalog's own id. This form has no
                    // id field -- it is keyed on the ICAO the admin typed -- so
                    // setting one would put a stray key into the form state and,
                    // on an edit, offer to rewrite the airport's primary key.
                    if ($key === 'id') {
                        continue;
                    }

                    if ($key === 'city') {
                        $key = 'location';
                    }

                    if ($key === 'country') {
                        $value = strtolower($value);
                    }

                    $set($key, $value);
                }

                if (count($airport) > 0) {
                    Notification::make('')
                        ->success()
                        ->title(__('airports.lookup_successful'))
                        ->send();
                } else {
                    Notification::make('')
                        ->danger()
                        ->title(__('airports.lookup_failed'))
                        ->body(__('airports.no_airport_found', ['icao' => $get('icao')]))
                        ->send();
                }
            });
    }
}
