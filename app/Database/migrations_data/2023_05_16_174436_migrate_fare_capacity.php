<?php

use App\Contracts\Migration;
use App\Models\PirepFare;

/**
 * Check if the PIREP fare has an empty capacity and update it from the subfleet
 */
return new class() extends Migration
{
    public function up(): void
    {
        $cached = [];
        $fareSvc = app(App\Services\FareService::class);
        $all_fares = PirepFare::with('pirep', 'pirep.aircraft', 'pirep.aircraft.subfleet')->get();

        /** @var PirepFare $fare */
        foreach ($all_fares as $fare) {
            if (empty($fare->pirep)) {
                // has capacity, skip
                continue;
            }
            if (!empty($fare->capacity)) {
                // has capacity, skip
                continue;
            }
            // look up the subfleet
            $subfleet = $fare->pirep->aircraft?->subfleet;
            if (empty($subfleet)) {
                continue;
            }

            if (empty($cached[$subfleet->id])) {
                $cached[$subfleet->id] = $fareSvc->getForSubfleet($subfleet);
            }

            /** @var \App\Models\Fare $sf */
            $sf = $cached[$subfleet->id]->where('code', $fare->code)->first();
            $fare->capacity = empty($sf) ? $fare->count : $sf->capacity;
            $fare->save();
        }
    }
};
