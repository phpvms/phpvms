{{-- One weather card. State comes from App\Features\Flights\Livewire\AirportWeather. --}}
<x-vms::weather-detail-card :label="$label" :icao="$icao" :state="$state" :metar="$metar" :observed-at="$observedAt" :empty-label="$emptyLabel">
    <x-slot:retry>
        <x-ts-button type="button" xs light wire:click="retry">Retry</x-ts-button>
    </x-slot:retry>
</x-vms::weather-detail-card>
