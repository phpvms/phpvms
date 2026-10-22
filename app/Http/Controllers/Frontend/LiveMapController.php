<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Models\Airport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class LiveMapController extends Controller
{
    /**
     * seven keeps its own server-rendered Leaflet page unchanged (blade path
     * below, maplibre-map-platform proposal.md non-goal). skylight gets a new
     * in-SPA page (map-surfaces spec, "skylight has its own live map page")
     * that polls `GET api/map/live` itself — the only thing this controller
     * hands it is the operator-configured poll interval and the resolved
     * initial camera centre, since flights, basemap/layers (Inertia's shared
     * `map` prop), and everything else the page needs is already reachable
     * client-side.
     */
    public function index(Request $request): View|InertiaResponse
    {
        return response()->themed(
            'LiveMap/Index',
            'livemap.index',
            spa: [
                'updateIntervalSeconds' => (int) setting('livemap.update_interval', 60),
                'initialCenter'         => $this->resolveInitialCenter(),
            ],
        );
    }

    /**
     * Where the live map's camera opens, before any flight is selected —
     * always at a globe-scale zoom (the client's job, not this method's).
     * `livemap.center_coords` first, since an operator may have set it
     * deliberately (seven's live map widget already reads the same key —
     * `App\Widgets\LiveMap::__invoke()` — and overriding it silently here
     * would contradict that). Falling back to the first hub airport when the
     * setting is absent: there is no per-airline hub in this schema
     * (`airports.hub` is a single global boolean; `hub_id` on `aircraft`/
     * `subfleets` is unrelated), so "the airline's hub" resolves to the
     * first airport with `hub = true`. `null` when neither resolves — the
     * client falls back to the package's own default centre.
     *
     * @return array{lat: float, lon: float}|null
     */
    private function resolveInitialCenter(): ?array
    {
        $configured = setting('livemap.center_coords');

        if (filled($configured)) {
            $parts = array_map(static fn (string $c): float => (float) trim($c), explode(',', (string) $configured));

            if (count($parts) === 2) {
                return ['lat' => $parts[0], 'lon' => $parts[1]];
            }
        }

        $hub = Airport::query()->byHub()->whereNotNull('lat')->whereNotNull('lon')->first();

        if ($hub !== null) {
            return ['lat' => (float) $hub->lat, 'lon' => (float) $hub->lon];
        }

        return null;
    }
}
