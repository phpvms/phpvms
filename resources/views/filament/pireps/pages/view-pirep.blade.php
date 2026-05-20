@php
    /** @var \App\Filament\Resources\Pireps\Pages\ViewPirep $this */
    /** @var \App\Models\Pirep $record */
    $record = $this->getRecord();
    $mapFeatures = $this->mapFeatures;
    $performance = $this->performance;
@endphp

<x-filament-panels::page>
    @include('filament.pireps.partials.detail.index', [
        'record'      => $record,
        'mapFeatures' => $mapFeatures,
        'performance' => $performance,
    ])
</x-filament-panels::page>
