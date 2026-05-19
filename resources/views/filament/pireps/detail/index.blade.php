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

            {{-- Finance card: summary + fares + transactions --}}
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

        @include('filament.pireps.detail.sidebar', ['record' => $record])
    </div>

    <footer class="fi-pirep-detail-v2-footer">
        phpVMS &copy; {{ now()->format('Y') }}
    </footer>
</div>
