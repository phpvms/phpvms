{{-- One weather card. State comes from App\Features\Flights\Livewire\AirportWeather. --}}
<section class="min-w-0 rounded-md p-4" aria-label="{{ $label }} weather">
    <header class="flex items-center justify-between gap-3">
        <p>{{ $label }}</p>
        <strong class="font-mono">{{ $icao ?? '—' }}</strong>
    </header>

    @if ($state === 'missing-station')
        <p class="mt-3 text-[11px]">{{ $emptyLabel ?? 'Weather station unavailable' }}</p>
    @elseif ($state === 'loading')
        <p class="mt-3 text-[11px]" role="status" aria-live="polite">Loading current report…</p>
    @elseif ($state === 'loaded' || $state === 'stale')
        @if ($state === 'stale')
            <p class="mt-3 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" role="status">Stale report</p>
        @endif
        <p class="my-3 break-words font-mono text-[11px] leading-relaxed">{{ $metar }}</p>
        <p class="text-[11px]">Observed {{ $observedAt ?? 'time unavailable' }}</p>
    @elseif ($state === 'missing')
        <p class="mt-3 text-[11px]" role="status">No current report for {{ $icao }}.</p>
    @else
        <div class="mt-3 flex items-center justify-between gap-3 text-[11px]" role="alert">
            <p>Weather provider error for {{ $icao }}.</p>
            <x-ts-button type="button" xs light wire:click="retry">Retry</x-ts-button>
        </div>
    @endif
</section>
