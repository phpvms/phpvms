<?php

namespace Modules\Awards\Awards;

use App\Contracts\Award;
use ErrorException;
use Illuminate\Support\Facades\Log;

/**
 * All award classes need to extend Award and implement the check() method
 * This award is based on the original PilotFlightAwards.php file but
 * changes the fields that it checks in the PIREP to confirm the Departure
 * and Arrival Airport ICAO codes.
 * This award means you can create an award for a pilot that complets a flight
 * from one airport to another.
 *
 * See: https://docs.phpvms.net/developers/awards
 */
class FlightRouteAwards extends Award
{
    /**
     * Set the name of this award class to make it easier to see when
     * assigning to a specific award
     *
     * @var string
     */
    public $name = 'Flight Route Award';

    /**
     * The description to show under the parameters field, so the admin knows
     * what the parameter actually controls. You can leave this blank if there
     * isn't a parameter.
     *
     * @var string
     */
    public $param_description = 'Departure Airport ICAO and Arrival Airport ICAO as XXXX:YYYY';

    /**
     * This method only needs to return a true or false of whether it should be awarded or not.
     *
     * If no parameter is passed in, just default it to XXXX:YYYY.
     *
     * @param null|mixed $dptarr
     */
    public function check($dptarr = null): bool
    {
        if ($this->user->last_pirep_id === null || empty($this->user->last_pirep)) {
            return false;
        }

        $dptarr = strtoupper(trim($dptarr));
        if (empty($dptarr)) {
            Log::error('FlightRouteAwards: empty departure/arrival string');

            return false;
        }

        try {
            [$dpt_icao, $arr_icao] = explode(':', $dptarr);
        } catch (ErrorException $e) {
            Log::error('FlightRouteAwards: Invalid departure/arrival, val="'.$dptarr.'\"');

            return false;
        }

        $dpt = $this->user->last_pirep->dpt_airport_id;
        $arr = $this->user->last_pirep->arr_airport_id;

        return $dpt === $dpt_icao && $arr === $arr_icao;
    }
}
