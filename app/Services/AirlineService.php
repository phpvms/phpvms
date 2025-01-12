<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Airline;
use App\Repositories\AirlineRepository;
use App\Repositories\FlightRepository;
use App\Repositories\PirepRepository;
use App\Repositories\SubfleetRepository;

class AirlineService extends Service
{
    public function __construct(
        private readonly AirlineRepository $airlineRepo,
        private readonly FlightRepository $flightRepo,
        private readonly PirepRepository $pirepRepo,
        private readonly SubfleetRepository $subfleetRepo
    ) {
    }

    /**
     * Create a new airline, and initialize the journal.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function createAirline(array $attr): Airline
    {
        /** @var Airline $airline */
        $airline = $this->airlineRepo->create($attr);
        $airline->refresh();

        return $airline;
    }

    /**
     * Can the airline be deleted? Check if there are flights, etc associated with it.
     */
    public function canDeleteAirline(Airline $airline): bool
    {
        // Check these asset counts in these repositories
        $repos = [
            $this->pirepRepo,
            $this->flightRepo,
            $this->subfleetRepo,
        ];

        $w = ['airline_id' => $airline->id];
        foreach ($repos as $repo) {
            if ($repo->count($w) > 0) {
                return false;
            }
        }

        return true;
    }
}
