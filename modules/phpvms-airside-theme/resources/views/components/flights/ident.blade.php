@props([
    'callsign',
    'dpt' => null,
    'arr' => null,
    'airline' => null,
    'logo' => null,
    'aircraft' => null,
    'href' => null,
    'size' => 'md',
    'stacked' => false,
])

{{--
  Flight identity (FlightIdentHeader). Props: callsign, dpt, arr (null shows "—"), airline (name, shown muted
  under the ident; its first two letters become the avatar fallback, "VA" when absent), logo (url), aircraft (e.g. "N123 · B738"),
  href (makes the whole block a link), size (md|lg), stacked (route on its own line, long ident truncates).
--}}
@php
    $mark = $airline ? mb_strtoupper(mb_substr(trim($airline), 0, 2)) : 'VA';
    $lg = $size === 'lg';
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['vms-flight-info flex min-w-0 max-w-full items-center', $lg ? 'gap-3' : 'gap-2.5', 'hover:underline' => $href]) }}>
    <span @class(['grid shrink-0 place-items-center overflow-hidden rounded-md font-semibold', $lg ? 'size-12 text-sm' : 'size-8 text-[11px]'])>
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $airline ?? $callsign }}" class="size-full object-contain" />
        @else
            {{ $mark }}
        @endif
    </span>
    <span class="grid min-w-0">
        <span @class(['flex min-w-0 leading-tight', $stacked ? 'flex-col items-start' : 'items-baseline gap-2'])>
            <span @class(['font-mono font-bold', $lg ? 'text-2xl' : '', 'max-w-full truncate' => $stacked, 'shrink-0' => ! $stacked])>{{ $callsign }}</span>
            <span @class(['inline-flex min-w-0 items-baseline gap-1.5 truncate font-mono', $lg ? 'text-2xl' : '', $stacked ? 'mt-0.5 font-medium' : 'font-bold'])>
                <span>{{ $dpt ?? '—' }}</span><span aria-hidden="true">→</span><span class="sr-only">to</span><span>{{ $arr ?? '—' }}</span>
            </span>
        </span>
        @if ($airline)
            <span @class(['vms-flight-info-airline mt-0.5 truncate text-dark-500 dark:text-dark-400', $lg ? 'text-sm' : 'text-xs'])>{{ $airline }}</span>
        @endif
        @if ($aircraft)
            <span class="mt-0.5 truncate text-sm">{{ $aircraft }}</span>
        @endif
    </span>
</{{ $tag }}>
