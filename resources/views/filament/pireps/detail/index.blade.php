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
        <div class="fi-pirep-detail-v2-main" x-data="{ activeTab: 'flight' }">
            {{-- Tab navigation: Flight / Flight Log / Finances --}}
            <div class="fi-pirep-detail-v2-main-tabs">
                <button
                    type="button"
                    class="fi-pirep-detail-v2-main-tab"
                    :class="{ 'active': activeTab === 'flight' }"
                    @click="activeTab = 'flight'"
                >
                    <svg class="fi-pirep-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 2L11 13"/>
                        <path d="M22 2L15 22L11 13L2 9L22 2Z"/>
                    </svg>
                    Flight
                </button>
                <button
                    type="button"
                    class="fi-pirep-detail-v2-main-tab"
                    :class="{ 'active': activeTab === 'log' }"
                    @click="activeTab = 'log'"
                >
                    <svg class="fi-pirep-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Flight Log
                </button>
                <button
                    type="button"
                    class="fi-pirep-detail-v2-main-tab"
                    :class="{ 'active': activeTab === 'finances' }"
                    @click="activeTab = 'finances'"
                >
                    <svg class="fi-pirep-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    Finances
                </button>
            </div>

            {{-- Flight tab panel --}}
            <div x-show="activeTab === 'flight'" x-cloak class="fi-pirep-detail-v2-tab-panel">
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
            </div>

            {{-- Flight Log tab panel --}}
            <div x-show="activeTab === 'log'" x-cloak class="fi-pirep-detail-v2-tab-panel">
                @include('filament.pireps.detail.flight-log')
            </div>

            {{-- Finances tab panel --}}
            <div x-show="activeTab === 'finances'" x-cloak class="fi-pirep-detail-v2-tab-panel">
                @php
                    $totalRevenue = $record->transactions->sum('credit');
                    $totalExpenses = $record->transactions->sum('debit');
                    $netCents = $totalRevenue - $totalExpenses;
                    $currency = setting('units.currency');

                    $fmtMoney = function (int $cents) use ($currency): string {
                        return \Illuminate\Support\Number::currency($cents / 100, $currency);
                    };

                    $fareRevenue = $record->fares->reduce(fn ($carry, $fare) => $carry + ($fare->count * $fare->price * 100), 0);
                @endphp
                <div class="fi-pirep-detail-v2-card fi-pirep-finance-card">
                    <div class="fi-pirep-detail-v2-card-head">
                        <h3>{{ __('pireps.finance') }}</h3>
                        <div class="actions">
                            <button type="button" class="fi-pirep-finance-recalculate-btn"
                                    wire:click="recalculateFinances">
                                {{ __('filament.recalculate_finances') }}
                            </button>
                        </div>
                    </div>
                    <div class="fi-pirep-detail-v2-card-body flush">
                        {{-- Summary row --}}
                        <div class="fi-pirep-fin-summary">
                            <div class="fi-pirep-fin-cell">
                                <div class="fi-pirep-fin-lbl">{{ __('common.revenue') }}</div>
                                <div class="fi-pirep-fin-val credit">{{ $fmtMoney($totalRevenue) }}</div>
                            </div>
                            <div class="fi-pirep-fin-cell">
                                <div class="fi-pirep-fin-lbl">{{ __('common.expenses') }}</div>
                                <div class="fi-pirep-fin-val debit">{{ $fmtMoney($totalExpenses) }}</div>
                            </div>
                            <div class="fi-pirep-fin-cell">
                                <div class="fi-pirep-fin-lbl">{{ __('common.net') }}</div>
                                <div class="fi-pirep-fin-val {{ $netCents >= 0 ? 'credit' : 'debit' }}">{{ $fmtMoney($netCents) }}</div>
                            </div>
                        </div>

                        {{-- Fares section --}}
                        @if ($record->fares->isNotEmpty())
                            <div class="fi-pirep-fin-section-title">{{ trans_choice('pireps.fare', 2) }}</div>
                            <table class="fi-pirep-fin-table">
                                <thead>
                                    <tr>
                                        <th>{{ trans_choice('pireps.fare', 1) }}</th>
                                        <th>{{ __('pireps.count') }}</th>
                                        <th>{{ __('common.price') }}</th>
                                        <th>{{ __('common.capacity') }}</th>
                                        <th class="fi-pirep-fin-ta-right">{{ __('common.revenue') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($record->fares as $fare)
                                        @php
                                            $fareRev = ($fare->count ?? 0) * ($fare->price ?? 0) * 100;
                                        @endphp
                                        <tr>
                                            <td>{{ $fare->name ?? $fare->code }} ({{ $fare->code }})</td>
                                            <td class="fi-pirep-fin-value">{{ $fare->count ?? '—' }}</td>
                                            <td class="fi-pirep-fin-value fi-pirep-fin-money">{{ $fare->price !== null ? \Illuminate\Support\Number::currency($fare->price, $currency) : '—' }}</td>
                                            <td class="fi-pirep-fin-value">{{ $fare->capacity ?? '—' }}</td>
                                            <td class="fi-pirep-fin-value fi-pirep-fin-ta-right fi-pirep-fin-money credit">{{ $fmtMoney((int) $fareRev) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        {{-- Transactions section --}}
                        @if ($record->transactions->isNotEmpty())
                            <div class="fi-pirep-fin-section-title">{{ trans_choice('pireps.transaction', 2) }}</div>
                            <table class="fi-pirep-fin-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('common.memo') }}</th>
                                        <th class="fi-pirep-fin-ta-right">{{ __('common.credit') }}</th>
                                        <th class="fi-pirep-fin-ta-right">{{ __('common.debit') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($record->transactions as $tx)
                                        <tr>
                                            <td>{{ $tx->memo ?? '—' }}</td>
                                            @if ($tx->credit > 0)
                                                <td class="fi-pirep-fin-value fi-pirep-fin-ta-right fi-pirep-fin-money credit">+{{ $fmtMoney($tx->credit) }}</td>
                                                <td></td>
                                            @elseif ($tx->debit > 0)
                                                <td></td>
                                                <td class="fi-pirep-fin-value fi-pirep-fin-ta-right fi-pirep-fin-money debit">−{{ $fmtMoney($tx->debit) }}</td>
                                            @else
                                                <td class="fi-pirep-fin-value fi-pirep-fin-ta-right">—</td>
                                                <td class="fi-pirep-fin-value fi-pirep-fin-ta-right">—</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
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
