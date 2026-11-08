@props(['mapView', 'flight' => null, 'ident' => false])

{{--
  A flight's route on the dotted SVG map, in a card. Props: mapView (App\Features\Maps\MapView, e.g. from
  RouteMaps; its crop aspect is RouteMaps::ASPECT, which the card matches), flight (FlightSummaryData), ident
  (bool: show the x-vms::flights.ident for `flight` over the bottom of the map). Default slot: more content over
  the map, under the ident. The overlay fades in from transparent, so the map shows through it.
--}}
<div {{ $attributes->class('vms-route-card relative overflow-hidden rounded-xl p-3') }}>
    <x-map.svg :map-view="$mapView" pattern="dots" :pin-radius="7" class="block aspect-video w-full" />

    @if (($ident && $flight) || $slot->isNotEmpty())
        <div class="vms-route-card-overlay absolute inset-x-0 bottom-0 grid gap-2 px-4 pt-16 pb-3">
            @if ($ident && $flight)
                <x-vms::flights.ident
                    size="lg"
                    :callsign="$flight->callsign"
                    :dpt="$flight->dpt"
                    :arr="$flight->arr"
                    :airline="$flight->airline?->name"
                    :logo="$flight->airline?->logo"
                />
            @endif
            {{ $slot }}
        </div>
    @endif
</div>
