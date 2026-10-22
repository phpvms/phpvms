@php
    /** @var \App\Filament\Resources\Pireps\Pages\ViewPirep $this */
    /** @var \App\Models\Pirep $record */
    $record = $this->getRecord();
    $performance = $this->performance;
@endphp

<x-filament-panels::page>
    @include('filament.pireps.partials.detail.index', [
        'record'        => $record,
        'performance'   => $performance,
        'extensionTabs' => $extensionTabs,
    ])
</x-filament-panels::page>
