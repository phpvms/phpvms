@props(['sector'])

{{-- The active sector: a link to the in-progress PIREP, or a muted placeholder. --}}
@php($route = $sector ? $sector->departureIcao.' → '.$sector->arrivalIcao : 'No active sector')

<div class="flex shrink-0 flex-col leading-tight whitespace-nowrap">
    <span class="text-[10px] font-medium tracking-wide text-dark-500 uppercase dark:text-dark-400">Active sector</span>
    @if ($sector)
        <a href="{{ url('/pireps/'.$sector->pirepId) }}" class="font-mono text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" title="{{ $sector->ident }} {{ $route }}" wire:navigate>{{ $route }}</a>
    @else
        <span class="font-mono text-xs text-dark-500 dark:text-dark-400">{{ $route }}</span>
    @endif
</div>
