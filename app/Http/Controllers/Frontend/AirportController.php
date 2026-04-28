<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Models\Airport;
use App\Models\Flight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Flash\Flash;

class AirportController extends Controller
{
    /**
     * Show the airport
     */
    public function show(string $id, Request $request): RedirectResponse|View
    {
        $id = strtoupper($id);
        // Support retrieval of deleted relationships
        $with_flights = [
            'airline' => function ($query) {
                return $query->withTrashed();
            },
            'arr_airport' => function ($query) {
                return $query->withTrashed();
            },
            'dpt_airport' => function ($query) {
                return $query->withTrashed();
            },
        ];

        $airport = Airport::with('files')->find($id);
        if (!$airport) {
            Flash::error('Airport not found!');

            return redirect(route('frontend.dashboard.index'));
        }

        $inbound_flights = Flight::with($with_flights)
            ->where('arr_airport_id', $id)
            ->where('active', 1)
            ->get();

        $outbound_flights = Flight::with($with_flights)
            ->where('dpt_airport_id', $id)
            ->where('active', 1)
            ->get();

        return view('airports.show', [
            'airport'          => $airport,
            'inbound_flights'  => $inbound_flights,
            'outbound_flights' => $outbound_flights,
        ]);
    }
}
