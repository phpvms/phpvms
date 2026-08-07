@php
    /** @var \App\Models\Pirep $record */
    $totalRevenue = $record->transactions->sum('credit');
    $totalExpenses = $record->transactions->sum('debit');
    $netCents = $totalRevenue - $totalExpenses;
    $currency = setting('units.currency');

    $fmtMoney = fn (int $cents): string => \Illuminate\Support\Number::currency($cents / 100, $currency);

    $marginPct = $totalRevenue > 0 ? round(($netCents / $totalRevenue) * 100) : null;
@endphp

<div class="strip [grid-template-columns:repeat(3,minmax(0,1fr))]" aria-label="Finance figures">
    <div class="strip__cell">
        <span class="strip__icon strip__icon--blue">@svg('tabler-cash')</span>
        <span class="strip__label">{{ __('common.revenue') }}</span>
        <span class="strip__value">{{ $fmtMoney($totalRevenue) }}</span>
        <span class="strip__note">{{ trans_choice('pireps.fare', 2) }}</span>
    </div>
    <div class="strip__cell">
        <span class="strip__icon strip__icon--amber">@svg('tabler-gas-station')</span>
        <span class="strip__label">{{ __('common.expenses') }}</span>
        <span class="strip__value">{{ $fmtMoney($totalExpenses) }}</span>
        <span class="strip__note">{{ trans_choice('pireps.transaction', 2) }}</span>
    </div>
    <div class="strip__cell">
        <span class="strip__icon strip__icon--teal">@svg('tabler-gauge')</span>
        <span class="strip__label">{{ __('common.net') }}</span>
        <span class="strip__value {{ $netCents >= 0 ? 'text-ok' : 'text-bad' }}">{{ $fmtMoney($netCents) }}</span>
        <span class="strip__note">{{ $marginPct !== null ? $marginPct.'% margin' : '—' }}</span>
    </div>
</div>

@if ($record->fares->isNotEmpty())
    <div class="panel__head rounded-none">
        <h2 class="panel__title">@svg('tabler-ticket') {{ trans_choice('pireps.fare', 2) }}</h2>
    </div>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th scope="col">{{ trans_choice('pireps.fare', 1) }}</th>
                    <th scope="col">Code</th>
                    <th scope="col" class="r">{{ __('pireps.count') }}</th>
                    <th scope="col" class="r">{{ __('common.capacity') }}</th>
                    <th scope="col" class="r">Load</th>
                    <th scope="col" class="r">{{ __('common.price') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($record->fares as $fare)
                    @php
                        $load = ($fare->capacity ?? 0) > 0 ? round((($fare->count ?? 0) / $fare->capacity) * 100, 1) : null;
                    @endphp
                    <tr>
                        <td class="tbl__primary">{{ $fare->name ?? $fare->code }}</td>
                        <td><span class="id">{{ $fare->code }}</span></td>
                        <td class="r"><span class="id">{{ $fare->count ?? '—' }}</span></td>
                        <td class="r"><span class="id">{{ $fare->capacity ?? '—' }}</span></td>
                        <td class="r"><span class="id">{{ $load !== null ? $load.'%' : '—' }}</span></td>
                        <td class="r"><span class="id">{{ $fare->price !== null ? \Illuminate\Support\Number::currency($fare->price, $currency) : '—' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="panel__head border-t border-line rounded-none">
    <h2 class="panel__title">@svg('tabler-receipt') Journal transactions <em>{{ $record->transactions->count() }}</em></h2>
    <div class="panel__tools">
        <button type="button" class="ghost-btn" wire:click="recalculateFinances">
            @svg('tabler-refresh') {{ __('filament.recalculate_finances') }}
        </button>
    </div>
</div>
@if ($record->transactions->isNotEmpty())
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th scope="col">{{ __('common.memo') }}</th>
                    <th scope="col">Group</th>
                    <th scope="col" class="r">{{ __('common.credit') }}</th>
                    <th scope="col" class="r">{{ __('common.debit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($record->transactions as $tx)
                    <tr>
                        <td class="tbl__primary">{{ $tx->memo ?? '—' }}</td>
                        <td>
                            @if (filled($tx->transaction_group))
                                <span class="chip chip--mute chip--plain">{{ $tx->transaction_group }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="r"><span class="id {{ $tx->credit > 0 ? 'text-ok' : 'text-ink-4' }}">{{ $tx->credit > 0 ? '+'.$fmtMoney($tx->credit) : '—' }}</span></td>
                        <td class="r"><span class="id {{ $tx->debit > 0 ? 'text-bad' : 'text-ink-4' }}">{{ $tx->debit > 0 ? '−'.$fmtMoney($tx->debit) : '—' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="panel__body panel__body--centred">
        <p class="text-ink-3 text-sm">No journal transactions for this PIREP yet.</p>
    </div>
@endif
