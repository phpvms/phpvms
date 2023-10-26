<x-filament-panels::page>
  {{ $this->form }}

  <button wire:click="test">click me for update</button>

  @livewire(\App\Filament\Widgets\AirlineFinanceChart::class, [
                'airline_id' => $this->filters['airline_id'],
                'start_date' => $this->filters['start_date'],
                'end_date'   => $this->filters['end_date'],
            ])

  @livewire(\App\Filament\Widgets\AirlineFinanceTable::class, [
                'airline_id' => $this->filters['airline_id'],
                'start_date' => $this->filters['start_date'],
                'end_date'   => $this->filters['end_date'],
            ])

</x-filament-panels::page>
