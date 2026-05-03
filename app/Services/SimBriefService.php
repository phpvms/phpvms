<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Models\Acars;
use App\Models\Enums\AcarsType;
use App\Models\Enums\AirframeSource;
use App\Models\Pirep;
use App\Models\SimBrief;
use App\Models\SimBriefAircraft;
use App\Models\SimBriefAirframe;
use App\Models\SimBriefLayout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SimBriefService extends Service
{
    /**
     * Check to see if the OFP exists server-side. If it does, download it and
     * cache it immediately
     *
     * @param User   $user      User who generated this
     * @param string $static_id The SimBrief OFP static ID
     * @param string $ofp_id    The SimBrief OFP ID
     * @param string $flight_id The flight ID
     * @param string $ac_id     The aircraft ID
     * @param array  $fares     Full list of fares for the flight
     */
    public function downloadOfp(
        User $user,
        string $static_id,
        string $ofp_id,
        string $flight_id,
        string $ac_id,
        array $fares = []
    ): ?SimBrief {
        try {
            $response = Http::timeout(30)
                ->withoutRedirecting()
                ->get(config('phpvms.simbrief_ofp_url'), [
                    'username'  => $user->simbrief_username,
                    'static_id' => $static_id,
                    'json'      => 'v2',
                ]);

            if ($response->status() !== 200) {
                return null;
            }
        } catch (ConnectionException $e) {
            Log::error('Simbrief HTTP Error: '.$e->getMessage());

            return null;
        }

        $json = $response->json();

        if (empty($json) || data_get($json, 'fetch.status') !== 'Success') {
            Log::error('Simbrief | No data returned for user: '.$user->id.' static_id: '.$static_id);

            return null;
        }

        Storage::put('simbrief/'.$ofp_id.'.json', $response->body());

        $attrs = [
            'user_id'       => $user->id,
            'flight_id'     => $flight_id,
            'aircraft_id'   => $ac_id,
            'ofp_json_path' => 'simbrief/'.$ofp_id.'.json',
        ];

        // encode the fares data to JSONß
        if ($fares !== []) {
            $attrs['fare_data'] = json_encode($fares);
        }

        // Save this into the Simbrief table, if it doesn't already exist
        return SimBrief::updateOrCreate(
            ['id' => $ofp_id],
            $attrs
        );
    }

    /**
     * Create a prefiled PIREP from a given brief.
     *
     * 1. Read from the XML the basic PIREP info (dep, arr), and then associate the PIREP
     *    to the flight ID
     * 2. Remove the flight ID from the SimBrief model and assign the pirep ID to the row
     *    at the end of the flight. Keep flight ID until the flight ends (pirep file).
     * 3. Update the planned flight route in the acars table
     * 4. Add additional flight fields (ones which match ACARS)
     *
     * @param SimBrief $simBrief    The briefing to create the PIREP from
     * @param bool     $keep_flight True keeps the flight_id, default is false
     */
    public function attachSimbriefToPirep($pirep, SimBrief $simBrief, $keep_flight = false): Pirep
    {
        $this->addRouteToPirep($pirep, $simBrief);

        $simBrief->pirep_id = $pirep->id;
        $simBrief->flight_id = empty($keep_flight) ? null : $pirep->flight_id;
        $simBrief->save();

        return $pirep;
    }

    /**
     * Add the route from a SimBrief flight plan to a PIREP
     *
     * @param Pirep $pirep
     */
    protected function addRouteToPirep($pirep, SimBrief $simBrief): Pirep
    {
        // Clear previous entries
        Acars::where(['pirep_id' => $pirep->id, 'type' => AcarsType::ROUTE])->delete();

        // Create the flight route
        $order = 1;

        foreach ($simBrief->ofp->navlog ?? [] as $fix) {
            if ($fix->type === 'apt' || $fix->ident === 'TOC' || $fix->ident === 'TOD') {
                continue;
            }

            $position = [
                'name'     => $fix->ident,
                'pirep_id' => $pirep->id,
                'type'     => AcarsType::ROUTE,
                'order'    => $order++,
                'lat'      => $fix->pos_lat,
                'lon'      => $fix->pos_long,
            ];

            $acars = new Acars($position);
            $acars->save();
        }

        return $pirep;
    }

    /**
     * Remove any expired entries from the SimBrief table.
     * Expired means there's a flight_id attached to it, but no pirep_id
     * (meaning it was never used for an actual flight)
     */
    public function removeExpiredEntries(): void
    {
        $expire_hours = setting('simbrief.expire_hours', 6);
        $expire_time = Carbon::now('UTC')->subHours($expire_hours);

        $briefs = SimBrief::where([
            ['pirep_id', null],
            ['created_at', '<=', $expire_time],
        ])->get();

        foreach ($briefs as $brief) {
            $brief->delete();

            // TODO: Delete any assets (Which assets ?)
        }
    }

    /**
     * Get Aircraft and Airframe Data from SimBrief
     * Insert or Update relevant models, for proper and detailed flight planning
     */
    public function getAircraftAndAirframes()
    {
        $url = config('phpvms.simbrief_airframes_url');
        $sbdata = Http::get($url);

        if ($sbdata->ok()) {
            Log::debug('SimBrief | Aircraft and Airframe data obtained');
            $json = $sbdata->json();

            // Update Or Create Aircraft Entries
            foreach ($json as $ac) {
                Log::debug('SimBrief | Importing Aircraft : '.$ac['aircraft_icao'].' '.$ac['aircraft_name']);

                foreach ($ac['airframes'] as $af) {
                    Log::debug('SimBrief | Importing Airframe : '.$af['airframe_name'].' '.$af['airframe_comments']);
                    SimBriefAirframe::updateOrCreate([
                        'icao' => $af['airframe_icao'],
                        'name' => $af['airframe_comments'],
                    ], [
                        'icao'        => $af['airframe_icao'],
                        'name'        => $af['airframe_comments'],
                        'airframe_id' => ($af['airframe_id'] != false) ? $af['pilot_id'].'_'.$af['airframe_id'] : null,
                        'source'      => AirframeSource::SIMBRIEF,
                        'details'     => json_encode($af),
                        'options'     => json_encode($af['airframe_options']),
                    ]);
                }

                unset($ac['airframes']);

                SimBriefAircraft::updateOrCreate([
                    'icao' => $ac['aircraft_icao'],
                ], [
                    'icao'    => $ac['aircraft_icao'],
                    'name'    => $ac['aircraft_name'],
                    'details' => json_encode($ac),
                ]);
            }
        } else {
            Log::error('SimBrief | An Error Occured while trying to get aircraft and airframe data!');
        }
    }

    /**
     * Get OFP Layouts from SimBrief
     * Insert or Update relevant model for proper flight planning
     */
    public function GetBriefingLayouts()
    {
        $url = config('phpvms.simbrief_layouts_url');
        $sbdata = Http::get($url);

        if ($sbdata->ok()) {
            Log::debug('SimBrief | OFP Layouts data obtained');
            $json = $sbdata->json();

            // Update Or Create Layout Entries
            foreach ($json['layouts'] as $sb) {
                Log::debug('SimBrief | Importing Layout : '.$sb['name_long']);

                SimBriefLayout::updateOrCreate([
                    'id' => $sb['id'],
                ], [
                    'id'        => $sb['id'],
                    'name'      => $sb['name_short'],
                    'name_long' => $sb['name_long'],
                ]);
            }
        } else {
            Log::error('SimBrief | An Error Occured while trying to get layout data!');
        }
    }
}
