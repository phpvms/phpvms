<?php

namespace Modules\Awards\Awards;

use App\Contracts\Award;

/**
 * Simple example of an awards class, where you can apply an award when a user
 * has 100 flights. All award classes need to extend Award and implement the check() method
 *
 * See: https://docs.phpvms.net/developers/awards
 */
class PilotFlightAwards extends Award
{
    /**
     * Set the name of this award class to make it easier to see when
     * assigning to a specific award
     *
     * @var string
     */
    public $name = 'Pilot Flights';

    /**
     * The description to show under the parameters field, so the admin knows
     * what the parameter actually controls. You can leave this blank if there
     * isn't a parameter.
     *
     * @var string
     */
    public $param_description = 'The number of flights at which to give this award';

    /**
     * If the user has over N flights, then we can give them this award. This method
     * only needs to return a true or false of whether it should be awarded or not.
     *
     * If no parameter is passed in, just default it to 100. You should check if there
     * is a parameter or not. You can call it whatever you want, since that would make
     * sense with the $param_description.
     *
     * @param int|null $number_of_flights The parameters passed in from the UI
     */
    public function check($number_of_flights = null): bool
    {
        if (!$number_of_flights) {
            $number_of_flights = 100;
        }

        return $this->user->flights >= $number_of_flights;
    }
}
