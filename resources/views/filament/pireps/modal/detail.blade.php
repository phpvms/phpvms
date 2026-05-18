@php
    /** @var \App\Models\Pirep $record */
    $record = $record ?? (isset($getRecord) ? $getRecord() : null);

    /** @var array<string, mixed> $mapFeatures */
    $mapFeatures = $mapFeatures ?? [];

    /** @var array<string, mixed>|null $performance */
    $performance = $performance ?? null;
@endphp

<div class="fi-pirep-detail-v2">
    @include('filament.pireps.detail.header', ['record' => $record])

    <div class="fi-pirep-detail-v2-layout">
        <div class="fi-pirep-detail-v2-main">
            @include('filament.pireps.detail.route-performance', [
                'record'       => $record,
                'mapFeatures'  => $mapFeatures,
                'performance'  => $performance,
            ])

            @include('filament.pireps.detail.notes', ['record' => $record])

            {{-- Comments — embedded RelationManager --}}
            <div class="fi-pirep-detail-v2-card">
                <div class="fi-pirep-detail-v2-card-head">
                    <h3>{{ trans_choice('pireps.comment', 2) }}</h3>
                </div>
                <div class="fi-pirep-detail-v2-card-body flush">
                    @livewire(
                        \App\Filament\Resources\Pireps\RelationManagers\CommentsRelationManager::class,
                        ['ownerRecord' => $record, 'pageClass' => \App\Filament\Resources\Pireps\Pages\ViewPirep::class],
                        key('pirep-comments-'.$record->id)
                    )
                </div>
            </div>

            {{-- Finance — fares + transactions embedded --}}
            <div class="fi-pirep-detail-v2-card">
                <div class="fi-pirep-detail-v2-card-head">
                    <h3>Finance</h3>
                </div>
                <div class="fi-pirep-detail-v2-card-body flush">
                    <div class="fi-pirep-detail-v2-fin-section-title">{{ trans_choice('pireps.fare', 2) }}</div>
                    @livewire(
                        \App\Filament\Resources\Pireps\RelationManagers\FaresRelationManager::class,
                        ['ownerRecord' => $record, 'pageClass' => \App\Filament\Resources\Pireps\Pages\ViewPirep::class],
                        key('pirep-fares-'.$record->id)
                    )
                    <div class="fi-pirep-detail-v2-fin-section-title">{{ trans_choice('pireps.transaction', 2) }}</div>
                    @livewire(
                        \App\Filament\Resources\Pireps\RelationManagers\TransactionsRelationManager::class,
                        ['ownerRecord' => $record, 'pageClass' => \App\Filament\Resources\Pireps\Pages\ViewPirep::class],
                        key('pirep-tx-'.$record->id)
                    )
                </div>
            </div>

        </div>

        @include('filament.pireps.detail.sidebar', ['record' => $record])
    </div>
</div>
