{{-- The dispatch header METAR. State comes from App\Features\Shell\Livewire\HeaderMetar. --}}
<div class="flex min-w-0 items-center gap-2 text-xs" aria-label="METAR">
    <span class="text-[10px] font-medium tracking-wide text-dark-500 uppercase dark:text-dark-400">METAR</span>

    @if ($state === 'loading')
        <span class="text-dark-500 dark:text-dark-400">Loading METAR…</span>
    @elseif ($state === 'missing-station')
        <span class="text-dark-500 dark:text-dark-400">No station</span>
    @elseif ($state === 'missing')
        <span class="text-dark-500 dark:text-dark-400">No METAR</span>
    @elseif ($state === 'error')
        <span class="text-dark-500 dark:text-dark-400">METAR unavailable</span>
        <x-ts-button xs light wire:click="retry">Retry</x-ts-button>
    @else
        <span class="max-w-xs truncate font-mono 2xl:max-w-md" title="{{ $metar }}">{{ $metar }}</span>

        @if ($state === 'stale' && $observedAt)
            @php($observed = \Illuminate\Support\Carbon::parse($observedAt)->utc())
            <time
                class="text-dark-500 dark:text-dark-400"
                datetime="{{ $observed->toIso8601String() }}"
                x-data
                x-text="'Stale · ' + new Date($el.dateTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })"
            >Stale · {{ $observed->format('H:i') }}</time>
        @endif
    @endif
</div>
