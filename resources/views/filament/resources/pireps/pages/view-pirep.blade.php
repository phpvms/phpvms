@php
    /** @var \Filament\Resources\Pages\ViewRecord $this */
    /** @var \App\Models\Pirep $record */
    $record = $this->getRecord();
@endphp

<x-filament-panels::page>
    @include('filament.pireps.modal.detail', ['record' => $record])
</x-filament-panels::page>
