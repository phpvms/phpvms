<?php

namespace Tests;

use App\Models\Airline;
use App\Models\Flight;
use App\Models\Journal;
use App\Models\Pirep;
use App\Services\AirlineService;
use Prettus\Validator\Exceptions\ValidatorException;

final class AirlineTest extends TestCase
{
    /** @var AirlineService */
    protected AirlineService $airlineSvc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');

        $this->airlineSvc = app(AirlineService::class);
    }

    /**
     * @throws ValidatorException
     */
    public function testAddAirline(): void
    {
        $attrs = Airline::factory()->make([
            'iata' => '',
        ])->toArray();

        $airline = $this->airlineSvc->createAirline($attrs);
        $this->assertNotNull($airline);

        // Ensure only a single journal is created
        $journals = Journal::where([
            'morphed_type' => Airline::class,
            'morphed_id'   => $airline->id,
        ])->get();

        $this->assertCount(1, $journals);

        // Add another airline, also blank IATA
        $attrs = Airline::factory()->make([
            'iata' => '',
        ])->toArray();
        $airline = $this->airlineSvc->createAirline($attrs);
        $this->assertNotNull($airline);
    }

    /**
     * Try deleting an airline which has flights/other assets that exist
     */
    public function testDeleteAirlineWithFlight(): void
    {
        $airline = Airline::factory()->create();
        Flight::factory()->create([
            'airline_id' => $airline->id,
        ]);

        $this->assertFalse($this->airlineSvc->canDeleteAirline($airline));
    }

    /**
     * Try deleting an airline with existing PIREPs
     */
    public function testDeleteAirlineWithPirep(): void
    {
        $airline = Airline::factory()->create();
        Pirep::factory()->create([
            'airline_id' => $airline->id,
        ]);

        $this->assertFalse($this->airlineSvc->canDeleteAirline($airline));
    }
}
