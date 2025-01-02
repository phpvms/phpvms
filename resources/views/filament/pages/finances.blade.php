<x-filament-panels::page>
  {{ $this->filtersForm }}

  @livewire(\App\Filament\Widgets\AirlineFinanceChart::class, (property_exists($this, 'filters') ? ['filters' => $this->filters] : []))

  @livewire(\App\Filament\Widgets\AirlineFinanceTable::class, (property_exists($this, 'filters') ? ['filters' => $this->filters] : []))

</x-filament-panels::page>
