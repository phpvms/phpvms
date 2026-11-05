@props(['shell', 'topbar' => false])

{{--
  The dispatch header, drawn as a rounded strip (see customizeShell() in the theme provider):
  the clocks, the previous and next flight, then the airline identity on the right. In the top bar layout
  (`topbar`) there is no sidebar, so the mobile menu button is left out.
--}}
@php
    $user = $shell->user();
    $chrome = $shell->pilotChrome();
    $airline = $user['airline'] ?? null;
    $station = $chrome?->station;
@endphp

<x-ts-layout.header sm :without-mobile-button="$topbar">
    <x-slot:left>
        <x-vms::shell.clocks :station="$station" />
    </x-slot:left>

    @if ($user)
        <x-slot:middle>
            <div class="hidden min-w-0 items-center gap-5 lg:flex" aria-label="Dispatch status">
                <span class="vms-dispatch-sep h-6 w-px shrink-0" aria-hidden="true"></span>
                <x-vms::shell.flights :previous="$shell->previousFlight()" :next="$shell->nextFlight()" />
            </div>
        </x-slot:middle>
    @endif

    <x-slot:right>
        <div class="flex shrink-0 items-center gap-3 whitespace-nowrap" aria-label="Airline identity">
            <span class="min-w-0 text-right">
                <span class="block truncate text-sm font-semibold">{{ $airline['name'] ?? $shell->appName() }}</span>
                @if (! empty($airline['icao']) || ! empty($airline['iata']))
                    <span class="block font-mono text-xs text-dark-500 dark:text-dark-400">{{ collect([$airline['icao'] ?? null, $airline['iata'] ?? null])->filter()->implode(' · ') }}</span>
                @endif
            </span>
            @if (! empty($airline['logo']))
                <img src="{{ $airline['logo'] }}" alt="" aria-hidden="true" class="size-9 shrink-0 object-contain" />
            @else
                <x-ts-avatar :text="mb_strtoupper(mb_substr((string) ($airline['icao'] ?? $shell->appName()), 0, 2))" sm />
            @endif
        </div>
    </x-slot:right>
</x-ts-layout.header>
