<?php

namespace App\Filament\Resources\Airports\Actions;

use App\Services\AirportService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class LookupAction
{
    public static function make(): Action
    {
        return Action::make('lookup')
            ->label(__('airports.lookup'))
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->action(function (Get $get, Set $set) {
                $airport = app(AirportService::class)->lookupAirport($get('icao'));

                foreach ($airport as $key => $value) {
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
