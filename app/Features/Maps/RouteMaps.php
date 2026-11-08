<?php

declare(strict_types=1);

namespace App\Features\Maps;

/**
 * The map view of a flight between two airports: the narrowest map that holds
 * both, cropped to them, with a pin on each and the great circle between.
 * The flight details drawer shows it.
 */
final readonly class RouteMaps
{
    public const float ASPECT = 16 / 9;

    /** Share of the route card's height under its ident band (x-vms::flights.route-card with `ident`). */
    public const float IDENT_INSET = 0.4;

    public function __construct(private MapResolver $resolver) {}

    /**
     * @param array{country: ?string, lat: ?float, lon: ?float, label?: ?string} $from
     * @param array{country: ?string, lat: ?float, lon: ?float, label?: ?string} $to
     * @param float                                                              $insetBottom share of the height covered at the bottom (see MapView::make)
     */
    public function view(array $from, array $to, float $insetBottom = 0.0): MapView
    {
        $map = $this->resolver->forPoints([
            ['country' => $from['country'], 'lat' => $from['lat'], 'lon' => $from['lon']],
            ['country' => $to['country'], 'lat' => $to['lat'], 'lon' => $to['lon']],
        ]);

        return MapView::make(
            $map,
            pins: [
                ['lat' => $from['lat'], 'lon' => $from['lon'], 'label' => $from['label'] ?? null],
                ['lat' => $to['lat'], 'lon' => $to['lon'], 'label' => $to['label'] ?? null],
            ],
            routes: [[[$from['lat'], $from['lon']], [$to['lat'], $to['lon']]]],
            fit: true,
            fitAspect: self::ASPECT,
            fitInsetBottom: $insetBottom,
        );
    }
}
