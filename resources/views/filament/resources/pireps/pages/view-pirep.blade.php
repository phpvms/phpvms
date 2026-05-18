@php
    /** @var \App\Filament\Resources\Pireps\Pages\ViewPirep $this */
    /** @var \App\Models\Pirep $record */
    $record = $this->getRecord();
    $mapFeatures = $this->mapFeatures;
@endphp

<x-filament-panels::page>
    @include('filament.pireps.modal.detail', ['record' => $record, 'mapFeatures' => $mapFeatures])
</x-filament-panels::page>
