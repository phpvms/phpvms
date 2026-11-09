{{-- The flight page's bid controls. State comes from App\Features\Flights\Livewire\FlightActions. --}}
@php
    $flight = $this->detail['flight'];
    $policy = $this->detail['policy'];
    $action = $flight->summary->primaryAction;
@endphp

<div class="flex flex-wrap items-center gap-2.5">
    @if ($action !== 'unavailable')
        <x-ts-button type="button" x-on:click="$dispatch('assignment-drawer.open', { flightId: '{{ $flight->summary->id }}' })">{{ $action === 'overview' ? 'Open bid overview' : 'Bid on flight' }}</x-ts-button>
    @else
        <x-ts-button type="button" disabled>Unavailable</x-ts-button>
    @endif

    @if ($policy->simbriefEnabled && (! $policy->simbriefRequiresBid || $action === 'overview'))
        <a href="{{ $flight->ofpPlanningUrl }}" wire:navigate class="text-xs font-semibold hover:underline">Generate OFP</a>
    @endif

    <x-render-hook :hook="new \App\Features\Hooks\Points\RenderingFlightActions($flight->summary, $policy, auth()->user(), $this->detail['bid'], null)" />
</div>
