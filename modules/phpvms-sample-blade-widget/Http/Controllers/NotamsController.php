<?php

namespace Modules\SampleBladeWidget\Http\Controllers;

use App\Contracts\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serves the "Station NOTAMs" Blade fragment for the skylight dashboard widget.
 *
 * THE BLADE ADVANTAGE (why this tier exists)
 * ------------------------------------------
 * Everything in show() runs ON THE SERVER. Only the rendered HTML is sent to
 * the browser — the query, the data source, any business rules, credentials,
 * etc. never leave the server. If this fetched from a real NOTAM provider,
 * queried the DB, or applied per-airline logic, none of that would be visible
 * to (or tamperable by) the client. That is the whole point of a Blade widget
 * versus a Vue widget: keep the logic server-side, ship only markup.
 *
 * Here we FAKE the "hidden server logic" with a tiny in-memory list — no
 * database, no external calls — so the sample is self-contained and safe to run.
 */
class NotamsController extends Controller
{
    /**
     * Render the NOTAMs fragment for a given ICAO.
     *
     * The skylight host shell (BladeWidget.vue, island mode) calls this endpoint
     * two ways:
     *   1. On mount: a plain credentialed GET (no query) → defaults to KJFK.
     *   2. On form submit: the shell intercepts the fragment's <form>, appends
     *      the ICAO field as a query string, and re-fetches this same endpoint —
     *      then swaps the returned HTML in place. So this one method handles both
     *      the initial render and every subsequent lookup.
     *
     * We must return a LAYOUT-LESS fragment (no @extends, no <html>): the shell
     * injects our HTML into an existing element on the dashboard.
     */
    public function show(Request $request): View
    {
        // Normalise the requested station. `KJFK` is the default the shell hits
        // on first mount. strtoupper keeps ICAO codes canonical.
        $icao = strtoupper(trim((string) $request->query('icao', 'KJFK')));

        // ---- Simulated "server-only" data source -------------------------
        // In a real addon this might be a NOTAM API call or a DB query. It lives
        // here, on the server, and never reaches the browser as code — only the
        // rendered rows below do. We seed a couple of stations and fall back to
        // an empty list for anything else, so the fragment can show its empty
        // state too.
        $byStation = [
            'KJFK' => [
                ['id' => 'A0123/26', 'summary' => 'RWY 04L/22R CLSD FOR MAINT', 'severity' => 'high'],
                ['id' => 'A0124/26', 'summary' => 'TWY B BTN B4 AND B6 CLSD',    'severity' => 'medium'],
                ['id' => 'A0125/26', 'summary' => 'ILS RWY 13L GP U/S',           'severity' => 'high'],
            ],
            'EGLL' => [
                ['id' => 'H1450/26', 'summary' => 'BIRD ACTIVITY VICINITY AD',    'severity' => 'low'],
                ['id' => 'H1451/26', 'summary' => 'RWY 09R/27L RVR EQIP U/S',     'severity' => 'medium'],
            ],
            'KLAX' => [
                ['id' => 'A2201/26', 'summary' => 'APRON WORK IN PROGRESS TERM 7', 'severity' => 'low'],
            ],
        ];

        // Unknown stations get an empty array → the view renders its empty state.
        $notams = $byStation[$icao] ?? [];

        // Return the layout-less fragment. compact() passes $icao + $notams in.
        return view('sample-blade-widget::notams', ['icao' => $icao, 'notams' => $notams]);
    }
}
