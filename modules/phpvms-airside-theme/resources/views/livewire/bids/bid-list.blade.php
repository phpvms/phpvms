{{-- My bids. State comes from App\Features\Bids\Livewire\BidList. --}}
@php
    $rows = $this->rows;
    $tours = $this->tours;
    $loose = $this->looseBids;
@endphp

<x-airside::page name="my-bids" label="My bids">
    <x-airside::page-header headline="Flights" title="My bids" description="Your reserved dispatches, ready to reopen and brief.">
        <x-slot:links>
            <x-ts-badge light>{{ count($rows) }} {{ count($rows) === 1 ? 'bid' : 'bids' }}</x-ts-badge>
        </x-slot:links>
    </x-airside::page-header>

    @if ($removeError)
        <x-airside::alert kind="error" aria-live="assertive">{{ $removeError }}</x-airside::alert>
    @endif

    @foreach ($tours as $group)
        <section wire:key="tour-{{ $group['id'] }}" class="grid gap-3" aria-labelledby="tour-group-{{ $group['id'] }}">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ts-badge color="primary" light sm>Tour</x-ts-badge>
                    <h2 id="tour-group-{{ $group['id'] }}" class="text-base font-semibold">{{ $group['name'] }}</h2>
                    <span class="font-mono text-[11px]">{{ count($group['rows']) }} {{ count($group['rows']) === 1 ? 'leg' : 'legs' }}</span>

                    {{-- Cancelling any leg ends the run, so the action belongs to the tour. --}}
                    <x-airside::confirm
                        prompt="Cancel the whole tour?"
                        confirm-label="Yes, cancel it"
                        keep-label="Keep flying"
                        wire:click="removeBid({{ json_encode($group['rows'][0]->bid->flightId) }})"
                    >
                        <x-slot:trigger>
                            <x-ts-button type="button" sm light>Cancel tour</x-ts-button>
                        </x-slot:trigger>
                    </x-airside::confirm>
                </div>
                <x-ts-button :href="route('frontend.tours.show', $group['id'])" wire:navigate sm outline>Tour overview →</x-ts-button>
            </header>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($group['rows'] as $row)
                    <x-airside::bids-card wire:key="bid-{{ $row->bid->id }}" :row="$row" />
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($loose)
        <section class="grid gap-3" aria-labelledby="loose-bids-heading">
            <header class="flex items-center gap-2">
                <h2 id="loose-bids-heading" class="text-base font-semibold">Bids</h2>
                <span class="font-mono text-[11px]">Expiring first</span>
            </header>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($loose as $row)
                    <x-airside::bids-card wire:key="bid-{{ $row->bid->id }}" :row="$row" />
                @endforeach
            </div>
        </section>
    @endif

    @if (! $rows)
        <x-airside::empty icon="check-circle" title="No bids yet" description="Reserve a flight from the manifest to keep it here for your next dispatch.">
            <x-slot:actions>
                <x-ts-button :href="route('frontend.flights.index')" wire:navigate light>Browse flights</x-ts-button>
            </x-slot:actions>
        </x-airside::empty>
    @endif
</x-airside::page>
