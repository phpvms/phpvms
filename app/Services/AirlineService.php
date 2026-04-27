<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Models\Airline;
use App\Models\Subfleet;
use App\Repositories\AirlineRepository;
use App\Repositories\FlightRepository;
use App\Repositories\PirepRepository;
use Prettus\Validator\Exceptions\ValidatorException;

class AirlineService extends Service
{
    public function __construct(
        private readonly AirlineRepository $airlineRepo,
        private readonly FlightRepository $flightRepo,
        private readonly PirepRepository $pirepRepo
    ) {}

    /**
     * Create a new airline, and initialize the journal
     *
     *
     * @throws ValidatorException
     */
    public function createAirline(array $attr): Airline
    {
        /** @var Airline $airline */
        $airline = $this->airlineRepo->create($attr);
        $airline->refresh();

        return $airline;
    }

    /**
     * Can the airline be deleted? Check if there are flights, etc associated with it
     */
    public function canDeleteAirline(Airline $airline): bool
    {
        $w = ['airline_id' => $airline->id];

        if ($this->pirepRepo->count($w) > 0) {
            return false;
        }

        if ($this->flightRepo->count($w) > 0) {
            return false;
        }

        return !Subfleet::where('airline_id', $airline->id)->exists();
    }
}
