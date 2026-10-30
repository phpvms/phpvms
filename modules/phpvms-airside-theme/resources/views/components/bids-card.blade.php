@props(['row'])

{{--
  One reserved dispatch (BidCard). Prop: row (a BidRowData). Renders the ident header, the ETE / Dist / Aircraft
  stats, the expiry, then the actions: Cancel (inline confirm), View or Generate OFP, and the bids.row.actions outlet.
  The Cancel button calls the parent Livewire component's removeBid(flightId).
--}}
@php
    $flight = $row->flight;
    $stats = [
        ['label' => 'ETE', 'value' => $flight?->blockTime],
        ['label' => 'Dist', 'value' => $flight?->distanceNm !== null ? $flight->distanceNm.'NM' : null],
        ['label' => 'Aircraft', 'value' => $row->aircraft ? $row->aircraft->registration.' · '.$row->aircraft->icaoType : null],
    ];
@endphp
<x-airside::card {{ $attributes->class('pv-bid-card') }}>
    <x-slot:header>
        <div class="flex items-start justify-between gap-2">
            @if ($flight)
                <x-airside::flight-ident
                    stacked
                    :callsign="$flight->callsign"
                    :dpt="$flight->dpt"
                    :arr="$flight->arr"
                    :airline="$flight->airline?->name"
                    :logo="$flight->airline?->logo"
                    :href="route('frontend.flights.show', $row->bid->flightId)"
                />
            @else
                <span class="font-mono text-ink-dim">{{ $row->bid->flightId }}</span>
            @endif

            @if ($row->tourName)
                <x-ts-badge color="primary" light sm>Tour{{ $row->tourLeg ? ' · Leg '.$row->tourLeg : '' }}</x-ts-badge>
            @endif
        </div>
    </x-slot:header>

    <x-airside::stats :items="$stats" :columns="3" />
    <p class="mt-2 text-xs text-ink-dim">
        @if ($row->expiresAt)
            @php($expires = \Illuminate\Support\Carbon::parse($row->expiresAt)->utc())
            Expires <time datetime="{{ $expires->toIso8601String() }}" x-data x-text="new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date($el.dateTime))">{{ $expires->format('M j, Y, g:i A') }} UTC</time>
        @else
            No expiry
        @endif
    </p>

    <x-slot:footer>
        <div class="flex w-full flex-wrap items-center justify-end gap-2">
            @if ($row->canRemove)
                <x-airside::confirm
                    :prompt="$row->tourName ? 'Cancelling this leg ends “'.$row->tourName.'”.' : 'Cancel this bid?'"
                    :confirm-label="$row->tourName ? 'Cancel the tour' : 'Cancel bid'"
                    keep-label="Keep"
                    wire:click="removeBid({{ json_encode($row->bid->flightId) }})"
                >
                    <x-slot:trigger>
                        <x-ts-button type="button" sm light>Cancel</x-ts-button>
                    </x-slot:trigger>
                </x-airside::confirm>
            @endif

            @if ($row->ofpUrl)
                <x-ts-button :href="$row->ofpUrl" wire:navigate sm outline>View OFP</x-ts-button>
            @elseif ($row->canGenerateSimBrief)
                <x-ts-button :href="route('frontend.ofp.planning', ['bid_id' => $row->bid->id])" wire:navigate sm outline>Generate OFP</x-ts-button>
            @endif

            <x-slot-outlet name="bids.row.actions" :context="['bid' => $row->bid, 'flight' => $row->flight]" />
        </div>
    </x-slot:footer>
</x-airside::card>
