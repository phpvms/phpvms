@props(['station'])

{{-- UTC and station-local clocks, ticking at each minute boundary. --}}
<div class="flex shrink-0 items-center gap-4 font-mono text-xs whitespace-nowrap" x-data="airsideClocks(@js($station?->timezone))">
    <span class="flex items-baseline gap-1.5">
        <span class="text-[10px] font-medium tracking-wide text-dark-500 uppercase dark:text-dark-400">UTC</span>
        <time x-text="utc">{{ gmdate('H:i') }}Z</time>
    </span>
    @if ($station?->timezone)
        <span class="hidden items-baseline gap-1.5 sm:flex">
            <span class="text-[10px] font-medium tracking-wide text-dark-500 uppercase dark:text-dark-400">Local</span>
            <time x-text="local ?? 'Unavailable'"></time>
        </span>
    @endif
</div>
