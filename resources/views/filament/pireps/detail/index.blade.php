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

            @livewire(
                \App\Livewire\Filament\PirepCommentThread::class,
                ['record' => $record],
                key('pirep-notes-comments-'.$record->id)
            )

            {{-- Fares --}}
            <div class="fi-pirep-detail-v2-card">
                <div class="fi-pirep-detail-v2-card-head">
                    <h3>{{ trans_choice('pireps.fare', 2) }}</h3>
                </div>
                <div class="fi-pirep-detail-v2-card-body flush">
                    @livewire(
                        \App\Filament\Resources\Pireps\RelationManagers\FaresRelationManager::class,
                        ['ownerRecord' => $record, 'pageClass' => \App\Filament\Resources\Pireps\Pages\ViewPirep::class],
                        key('pirep-fares-'.$record->id)
                    )
                </div>
            </div>

            {{-- Transactions --}}
            <div class="fi-pirep-detail-v2-card">
                <div class="fi-pirep-detail-v2-card-head">
                    <h3>{{ trans_choice('pireps.transaction', 2) }}</h3>
                </div>
                <div class="fi-pirep-detail-v2-card-body flush">
                    @livewire(
                        \App\Filament\Resources\Pireps\RelationManagers\TransactionsRelationManager::class,
                        ['ownerRecord' => $record, 'pageClass' => \App\Filament\Resources\Pireps\Pages\ViewPirep::class],
                        key('pirep-tx-'.$record->id)
                    )
                </div>
            </div>

            {{-- Net total --}}
            @php
                $netCents = $record->transactions->reduce(
                    fn (int $carry, $tx): int => $carry + (int) $tx->credit - (int) $tx->debit,
                    0,
                );
                $netFormatted = \Illuminate\Support\Number::currency(
                    $netCents / 100,
                    setting('units.currency'),
                );
            @endphp
            <div class="fi-pirep-detail-v2-card">
                <div class="fi-pirep-detail-v2-card-body flush">
                    <div class="fi-pirep-net-row {{ $netCents >= 0 ? 'positive' : 'negative' }}">
                        <span class="lbl">{{ __('common.net') }}</span>
                        <span class="amt">{{ $netFormatted }}</span>
                    </div>
                </div>
            </div>

        </div>

        @include('filament.pireps.detail.sidebar', ['record' => $record])
    </div>

    <footer class="fi-pirep-detail-v2-footer">
        phpVMS &copy; {{ now()->format('Y') }}
    </footer>
</div>
