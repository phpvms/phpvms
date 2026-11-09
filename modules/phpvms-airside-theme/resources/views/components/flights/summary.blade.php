@props([
    'callsign',
    'airline' => null,
    'logo' => null,
    'href' => null,
])

{{--
  Flight summary: airline logo plus flight information. Props: callsign, airline (name or null; first two
  letters become the logo fallback, "VA" when absent), logo (url or null), href (makes the callsign a link).
  Default slot: the pills row. Named slot `button`: replaces the callsign text, e.g. with a focusable
  <button> (not named `callsign`, which would shadow the prop); when given, href is ignored. Extra attributes and classes merge onto the root.
--}}
@php
    $mark = $airline ? mb_strtoupper(mb_substr(trim($airline), 0, 2)) : 'VA';
@endphp
<div {{ $attributes->class(['vms-flight-summary flex min-w-0 items-start gap-3']) }}>
    <span class="vms-flight-summary-logo grid size-11 shrink-0 place-items-center overflow-hidden rounded-lg text-sm font-semibold">
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $airline ?? $callsign }}" class="size-full object-contain" />
        @else
            {{ $mark }}
        @endif
    </span>
    <div class="vms-flight-summary-text grid min-w-0 gap-1">
        <div class="min-w-0 text-base leading-tight font-bold">
            @if (isset($button))
                {{ $button }}
            @elseif ($href)
                <a href="{{ $href }}" class="hover:underline">{{ $callsign }}</a>
            @else
                {{ $callsign }}
            @endif
        </div>
        @if ($airline)
            <p class="m-0 truncate text-xs">{{ $airline }}</p>
        @endif
        @if (! $slot->isEmpty())
            <div class="vms-flight-pills flex flex-wrap items-center gap-1.5">{{ $slot }}</div>
        @endif
    </div>
</div>
