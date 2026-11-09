@props(['previous' => null, 'next' => null])

{{--
  The header's flight status: the previous flight (a link to its PIREP) and, when there is a bid, the next flight
  (a link to My bids), with its tour when the bid is a tour leg. Props: previous and next, from ShellData's
  previousFlight() and nextFlight().
--}}
<div {{ $attributes->class('vms-shell-flights flex shrink-0 items-center gap-6 leading-tight whitespace-nowrap') }}>
    <div class="flex flex-col">
        <span class="text-[10px] font-medium tracking-wide text-dark-500 uppercase dark:text-dark-400">Previous flight</span>
        @if ($previous)
            <a href="{{ $previous['url'] }}" class="font-mono text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" title="{{ $previous['ident'] }} {{ $previous['route'] }}" wire:navigate>{{ $previous['route'] }}</a>
        @else
            <span class="font-mono text-xs text-dark-500 dark:text-dark-400">None yet</span>
        @endif
    </div>

    @if ($next)
        <div class="flex flex-col">
            <span class="text-[10px] font-medium tracking-wide text-dark-500 uppercase dark:text-dark-400">
                @if ($next['tour'])
                    Next flight · <a href="{{ $next['tourUrl'] }}" class="hover:underline" wire:navigate>{{ $next['tour'] }}</a>
                @else
                    Next flight
                @endif
            </span>
            <a href="{{ $next['url'] }}" class="font-mono text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" title="{{ $next['ident'] }} {{ $next['route'] }}" wire:navigate>{{ $next['ident'] }} {{ $next['route'] }}</a>
        </div>
    @endif
</div>
