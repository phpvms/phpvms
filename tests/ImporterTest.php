<?php

namespace Tests;

use App\Contracts\ImportExport;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\AircraftStatus;
use App\Models\Enums\Days;
use App\Models\Enums\ExpenseType;
use App\Models\Enums\FareType;
use App\Models\Enums\FlightType;
use App\Models\Expense;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\FlightFieldValue;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Services\ExportService;
use App\Services\FareService;
use App\Services\ImportExport\AircraftExporter;
use App\Services\ImportExport\AirportExporter;
use App\Services\ImportExport\FlightExporter;
use App\Services\ImportService;
use Illuminate\Support\Facades\Storage;
use League\Csv\CannotInsertRecord;

final class ImporterTest extends TestCase
{
    private ImportExport $importBaseClass;

    private ImportService $importSvc;

    private FareService $fareSvc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importBaseClass = new ImportExport();
        $this->importSvc = app(ImportService::class);
        $this->fareSvc = app(FareService::class);

        Storage::fake('local');
    }

    /**
     * Add some of the basic data needed to properly import the flights.csv file
     *
     * @return mixed
     */
    protected function insertFlightsScaffoldData(): array
    {
        $fare_svc = app(FareService::class);

        $al = [
            'icao' => 'VMS',
            'name' => 'phpVMS Airlines',
        ];

        $airline = Airline::factory()->create($al);
        $subfleet = Subfleet::factory()->create(['type' => 'A32X']);

        // Add the economy class
        $fare_economy = Fare::factory()->create(['code' => 'Y', 'capacity' => 150]);
        $fare_svc->setForSubfleet($subfleet, $fare_economy);

        $fare_business = Fare::factory()->create(['code' => 'B', 'capacity' => 20]);
        $fare_svc->setForSubfleet($subfleet, $fare_business);

        // Add first class
        $fare_first = Fare::factory()->create(['code' => 'F', 'capacity' => 10]);
        $fare_svc->setForSubfleet($subfleet, $fare_first);

        return [$airline, $subfleet];
    }

    /**
     * Test the parsing of different field/column which can be used
     * for specifying different field values
     */
    public function test_convert_stringto_objects(): void
    {
        $tests = [
            [
                'input'    => '',
                'expected' => [],
            ],
            [
                'input'    => 'gate',
                'expected' => ['gate'],
            ],
            [
                'input'    => 'gate;cost index',
                'expected' => [
                    'gate',
                    'cost index',
                ],
            ],
            [
                'input'    => 'gate=B32;cost index=100',
                'expected' => [
                    'gate'       => 'B32',
                    'cost index' => '100',
                ],
            ],
            [
                'input'    => 'Y?price=200&cost=100; F?price=1200',
                'expected' => [
                    'Y' => [
                        'price' => 200,
                        'cost'  => 100,
                    ],
                    'F' => [
                        'price' => 1200,
                    ],
                ],
            ],
            [
                'input'    => 'Y?price&cost; F?price=1200',
                'expected' => [
                    'Y' => [
                        'price',
                        'cost',
                    ],
                    'F' => [
                        'price' => 1200,
                    ],
                ],
            ],
            [
                'input'    => 'Y; F?price=1200',
                'expected' => [
                    0   => 'Y',
                    'F' => [
                        'price' => 1200,
                    ],
                ],
            ],
            [
                'input'    => 'Y?;F?price=1200',
                'expected' => [
                    'Y' => [],
                    'F' => [
                        'price' => 1200,
                    ],
                ],
            ],
            [
                'input'    => 'Departure Gate=4;Arrival Gate=C61',
                'expected' => [
                    'Departure Gate' => '4',
                    'Arrival Gate'   => 'C61',
                ],
            ],
            // Blank values omitted
            [
                'input'    => 'gate; ',
                'expected' => [
                    'gate',
                ],
            ],
        ];

        foreach ($tests as $test) {
            $parsed = $this->importBaseClass->parseMultiColumnValues($test['input']);
            $this->assertEquals($test['expected'], $parsed);
        }
    }

    /**
     * Tests for converting the different object/array key values
     * into the format that we use in CSV files
     */
    public function test_convert_object_to_string(): void
    {
        $tests = [
            [
                'input'    => '',
                'expected' => '',
            ],
            [
                'input'    => ['gate'],
                'expected' => 'gate',
            ],
            [
                'input' => [
                    'gate',
                    'cost index',
                ],
                'expected' => 'gate;cost index',
            ],
            [
                'input' => [
                    'gate'       => 'B32',
                    'cost index' => '100',
                ],
                'expected' => 'gate=B32;cost index=100',
            ],
            [
                'input' => [
                    'Y' => [
                        'price' => 200,
                        'cost'  => 100,
                    ],
                    'F' => [
                        'price' => 1200,
                    ],
                ],
                'expected' => 'Y?price=200&cost=100;F?price=1200',
            ],
            [
                'input' => [
                    'Y' => [
                        'price',
                        'cost',
                    ],
                    'F' => [
                        'price' => 1200,
                    ],
                ],
                'expected' => 'Y?price&cost;F?price=1200',
            ],
            [
                'input' => [
                    'Y' => [
                        'price',
                        'cost',
                    ],
                    'F' => [],
                ],
                'expected' => 'Y?price&cost;F',
            ],
            [
                'input' => [
                    0   => 'Y',
                    'F' => [
                        'price' => 1200,
                    ],
                ],
                'expected' => 'Y;F?price=1200',
            ],
            [
                'input' => [
                    'Departure Gate' => '4',
                    'Arrival Gate'   => 'C61',
                ],
                'expected' => 'Departure Gate=4;Arrival Gate=C61',
            ],
        ];

        foreach ($tests as $test) {
            $parsed = $this->importBaseClass->objectToMultiString($test['input']);
            $this->assertEquals($test['expected'], $parsed);
        }
    }

    /**
     * Test exporting all the flights to a file
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws CannotInsertRecord
     */
    public function test_aircraft_exporter(): void
    {
        $aircraft = Aircraft::factory()->create();

        $exporter = new AircraftExporter();
        $exported = $exporter->export($aircraft);

        $this->assertEquals($aircraft->iata, $exported['iata']);
        $this->assertEquals($aircraft->icao, $exported['icao']);
        $this->assertEquals($aircraft->name, $exported['name']);
        $this->assertEquals($aircraft->zfw, $exported['zfw']);
        $this->assertEquals($aircraft->subfleet->type, $exported['subfleet']);

        $importer = app(ImportService::class);
        $exporter = app(ExportService::class);

        $collection = collect([$aircraft]);
        $file = $exporter->exportAircraft($collection);

        $status = $importer->importAircraft($file);
        $this->assertCount(1, $status['success']);
        $this->assertCount(0, $status['errors']);
    }

    /**
     * Test exporting all the flights to a file
     *
     * @throws CannotInsertRecord
     */
    public function test_airport_exporter(): void
    {
        $airport_name = 'Adolfo Suárez Madrid–Barajas Airport';

        $airport = Airport::factory()->create([
            'name' => $airport_name,
        ]);

        $exporter = new AirportExporter();
        $exported = $exporter->export($airport);

        $this->assertEquals($airport->iata, $exported['iata']);
        $this->assertEquals($airport->icao, $exported['icao']);
        $this->assertEquals($airport->name, $exported['name']);

        $importer = app(ImportService::class);
        $exporter = app(ExportService::class);
        $file = $exporter->exportAirports(collect([$airport]));
        $status = $importer->importAirports($file);

        $this->assertCount(1, $status['success']);
        $this->assertCount(0, $status['errors']);
    }

    /**
     * Test exporting all the flights to a file
     *
     * @throws CannotInsertRecord
     */
    public function test_flight_exporter(): void
    {
        $fareSvc = app(FareService::class);

        [$airline, $subfleet] = $this->insertFlightsScaffoldData();
        $subfleet2 = Subfleet::factory()->create(['type' => 'B74X']);

        $fareY = Fare::where('code', 'Y')->first();
        $fareF = Fare::where('code', 'F')->first();

        $flight = Flight::factory()->create([
            'airline_id'  => $airline->id,
            'flight_type' => 'J',
            'days'        => Days::getDaysMask([
                Days::TUESDAY,
                Days::SUNDAY,
            ]),
        ]);

        $flight->subfleets()->syncWithoutDetaching([$subfleet->id, $subfleet2->id]);

        //
        $fareSvc->setForFlight($flight, $fareY, ['capacity' => '100']);
        $fareSvc->setForFlight($flight, $fareF);

        // Add some custom fields
        FlightFieldValue::create([
            'flight_id' => $flight->id,
            'name'      => 'Departure Gate',
            'value'     => '4',
        ]);

        FlightFieldValue::create([
            'flight_id' => $flight->id,
            'name'      => 'Arrival Gate',
            'value'     => 'C41',
        ]);

        // Test the conversion

        $exporter = new FlightExporter();
        $exported = $exporter->export($flight);

        $this->assertEquals('27', $exported['days']);
        $this->assertEquals('VMS', $exported['airline']);
        $this->assertEquals($flight->flight_time, $exported['flight_time']);
        $this->assertEquals('J', $exported['flight_type']);
        $this->assertEquals('A32X;B74X', $exported['subfleets']);
        $this->assertEquals('Y?capacity=100;F', $exported['fares']);
        $this->assertEquals('Departure Gate=4;Arrival Gate=C41', $exported['fields']);

        $importer = app(ImportService::class);
        $exporter = app(ExportService::class);
        $file = $exporter->exportFlights(collect([$flight]));
        $status = $importer->importFlights($file);
        $this->assertCount(1, $status['success']);
        $this->assertCount(0, $status['errors']);
    }

    /**
     * Try importing the aicraft in the airports. Should fail
     */
    public function test_invalid_file_import(): void
    {
        // $this->expectException(ValidationException::class);
        $file_path = base_path('tests/data/aircraft.csv');
        $status = $this->importSvc->importAirports($file_path);
        $this->assertCount(2, $status['errors']);
    }

    /**
     * Try importing the aicraft in the airports. Should fail because of
     * empty/invalid rows
     */
    public function test_empty_cols(): void
    {
        $file_path = base_path('tests/data/expenses_empty_rows.csv');
        $status = $this->importSvc->importExpenses($file_path);
        $this->assertCount(8, $status['success']);
        $this->assertCount(0, $status['errors']);
    }

    /**
     * @throws \League\Csv\CannotInsertRecord
     */
    public function test_expense_exporter(): void
    {
        $expenses = Expense::factory(10)->create();

        /** @var ExportService $exporter */
        $exporter = app(ExportService::class);
        $exporter->exportExpenses($expenses);
    }

    /**
     * Test the importing of expenses
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function test_expense_importer(): void
    {
        $airline = Airline::factory()->create(['icao' => 'VMS']);
        $subfleet = Subfleet::factory()->create(['type' => '744-3X-RB211']);
        $aircraft = Aircraft::factory()->create([
            'subfleet_id'  => $subfleet->id,
            'registration' => '001Z',
        ]);

        $file_path = base_path('tests/data/expenses.csv');
        $status = $this->importSvc->importExpenses($file_path);

        $this->assertCount(8, $status['success']);
        $this->assertCount(0, $status['errors']);

        $expenses = Expense::all();

        $on_airline = $expenses->firstWhere('name', 'Per-Flight (multiplier, on airline)');
        $this->assertEquals(200, $on_airline->amount);
        $this->assertEquals($airline->id, $on_airline->airline_id);

        $pf = $expenses->firstWhere('name', 'Per-Flight (no muliplier)');
        $this->assertEquals(100, $pf->amount);
        $this->assertEquals(ExpenseType::FLIGHT, $pf->type);

        $catering = $expenses->firstWhere('name', 'Catering Staff');
        $this->assertEquals(1000, $catering->amount);
        $this->assertEquals(ExpenseType::DAILY, $catering->type);
        $this->assertInstanceOf(Subfleet::class, $catering->ref_model);
        $this->assertEquals($subfleet->id, $catering->ref_model->id);
        $this->assertEquals($subfleet->id, $catering->ref_model_id);

        $mnt = $expenses->firstWhere('name', 'Maintenance');
        $this->assertInstanceOf(Aircraft::class, $mnt->ref_model);
        $this->assertEquals($aircraft->id, $mnt->ref_model_id);
        $this->assertEquals($aircraft->id, $mnt->ref_model->id);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function test_fare_importer(): void
    {
        $file_path = base_path('tests/data/fares.csv');
        $status = $this->importSvc->importFares($file_path);

        $this->assertCount(4, $status['success']);
        $this->assertCount(0, $status['errors']);

        $fares = Fare::all();

        $y_class = $fares->firstWhere('code', 'Y');
        $this->assertEquals('Economy', $y_class->name);
        $this->assertEquals(FareType::PASSENGER, $y_class->type);
        $this->assertEquals(100, $y_class->price);
        $this->assertEquals(0, $y_class->cost);
        $this->assertEquals(200, $y_class->capacity);
        $this->assertTrue($y_class->active);
        $this->assertEquals('This is the economy class', $y_class->notes);

        $b_class = $fares->firstWhere('code', 'B');
        $this->assertEquals('Business', $b_class->name);
        $this->assertEquals(FareType::PASSENGER, $b_class->type);
        $this->assertEquals(500, $b_class->price);
        $this->assertEquals(250, $b_class->cost);
        $this->assertEquals(10, $b_class->capacity);
        $this->assertEquals('This is business class', $b_class->notes);
        $this->assertFalse($b_class->active);

        $f_class = $fares->firstWhere('code', 'F');
        $this->assertEquals('First-Class', $f_class->name);
        $this->assertEquals(FareType::PASSENGER, $f_class->type);
        $this->assertEquals(800, $f_class->price);
        $this->assertEquals(350, $f_class->cost);
        $this->assertEquals(5, $f_class->capacity);
        $this->assertEquals('', $f_class->notes);
        $this->assertTrue($f_class->active);

        $cargo = $fares->firstWhere('code', 'C');
        $this->assertEquals('Cargo', $cargo->name);
        $this->assertEquals(FareType::CARGO, $cargo->type);
        $this->assertEquals(20, $cargo->price);
        $this->assertEquals(0, $cargo->cost);
        $this->assertEquals(10, $cargo->capacity);
        $this->assertEquals('', $cargo->notes);
        $this->assertTrue($cargo->active);
    }

    /**
     * Test the flight importer
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function test_flight_importer(): void
    {
        [$airline, $subfleet] = $this->insertFlightsScaffoldData();

        $file_path = base_path('tests/data/flights.csv');
        $status = $this->importSvc->importFlights($file_path);

        $this->assertCount(3, $status['success']);
        $this->assertCount(1, $status['errors']);

        // See if it imported
        /** @var Flight $flight */
        $flight = Flight::where([
            'airline_id'    => $airline->id,
            'flight_number' => '1972',
        ])->first();

        $this->assertNotNull($flight);

        // Check the flight itself
        $this->assertEquals('KAUS', $flight->dpt_airport_id);
        $this->assertEquals('KJFK', $flight->arr_airport_id);
        $this->assertEquals('0810 CST', $flight->dpt_time);
        $this->assertEquals('1235 EST', $flight->arr_time);
        $this->assertEquals('350', $flight->level);
        $this->assertEquals(1477, $flight->distance->internal());
        $this->assertEquals('207', $flight->flight_time);
        $this->assertEquals(FlightType::SCHED_PAX, $flight->flight_type);
        $this->assertEquals('ILEXY2 ZENZI LFK ELD J29 MEM Q29 JHW J70 STENT J70 MAGIO J70 LVZ LENDY6', $flight->route);
        $this->assertEquals('Just a flight', $flight->notes);
        $this->assertTrue($flight->active);

        // Test that the days were set properly
        $this->assertTrue($flight->on_day(Days::MONDAY));
        $this->assertTrue($flight->on_day(Days::FRIDAY));
        $this->assertFalse($flight->on_day(Days::TUESDAY));

        // Check the custom fields entered
        $fields = FlightFieldValue::where([
            'flight_id' => $flight->id,
        ])->get();

        $this->assertCount(2, $fields);
        $dep_gate = $fields->firstWhere('name', 'Departure Gate');
        $this->assertEquals('4', $dep_gate['value']);

        $dep_gate = $fields->firstWhere('name', 'Arrival Gate');
        $this->assertEquals('C41', $dep_gate['value']);

        // Check the fare class
        $fares = $this->fareSvc->getFareWithOverrides(null, $flight->fares);
        $this->assertCount(3, $fares);

        $first = $fares->firstWhere('code', 'Y');
        $this->assertEquals(300, $first->price);
        $this->assertEquals(100, $first->cost);
        $this->assertEquals(130, $first->capacity);

        $first = $fares->firstWhere('code', 'F');
        $this->assertEquals(600, $first->price);
        $this->assertEquals(400, $first->cost);
        $this->assertEquals(10, $first->capacity);

        // Check the subfleets
        $subfleets = $flight->subfleets;
        $this->assertCount(1, $subfleets);
        $this->assertNotEquals('A32X', $subfleets[0]->name);

        $flight = Flight::where([
            'airline_id'    => $airline->id,
            'flight_number' => '999',
        ])->first();
        $subfleets = $flight->subfleets;
        $this->assertCount(2, $subfleets);
        $this->assertEquals('B737', $subfleets[1]->type);
        $this->assertEquals('B737', $subfleets[1]->name);
    }

    /**
     * Test the flight importer
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function test_flight_importer_empty_custom_fields(): void
    {
        [$airline, $subfleet] = $this->insertFlightsScaffoldData();

        $file_path = base_path('tests/data/flights_empty_fields.csv');
        $status = $this->importSvc->importFlights($file_path);

        $this->assertCount(1, $status['success']);
        $this->assertCount(0, $status['errors']);

        // See if it imported
        $flight = Flight::where([
            'airline_id'    => $airline->id,
            'flight_number' => '1972',
        ])->first();

        $this->assertNotNull($flight);

        // Check the custom fields entered
        $fields = FlightFieldValue::where([
            'flight_id' => $flight->id,
        ])->get();

        $this->assertCount(0, $fields);
    }

    /**
     * Test the flight importer with "core" argument
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function test_flight_importer_core(): void
    {
        [$airline, $subfleet] = $this->insertFlightsScaffoldData();

        $file_path = base_path('tests/data/flights.csv');
        $status = $this->importSvc->importFlights($file_path, 'core');

        $this->assertCount(3, $status['success']);
        $this->assertCount(1, $status['errors']);

        // Additional assertions for "core" argument can be added here
    }

    /**
     * Test the flight importer with "all" argument
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function test_flight_importer_all(): void
    {
        [$airline, $subfleet] = $this->insertFlightsScaffoldData();

        $file_path = base_path('tests/data/flights.csv');
        $status = $this->importSvc->importFlights($file_path, 'all');

        $this->assertCount(3, $status['success']);
        $this->assertCount(1, $status['errors']);

        // Additional assertions for "all" argument can be added here
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function test_aircraft_importer(): void
    {
        Airline::factory()->create();
        // $subfleet = \App\Models\Subfleet::factory()->create(['type' => 'A32X']);

        $file_path = base_path('tests/data/aircraft.csv');
        $status = $this->importSvc->importAircraft($file_path);

        $this->assertCount(1, $status['success']);
        $this->assertCount(1, $status['errors']);

        // See if it imported
        $aircraft = Aircraft::where([
            'registration' => 'N309US',
        ])->first();

        $this->assertNotNull($aircraft);
        $this->assertNotNull($aircraft->hex_code);
        $this->assertNotNull($aircraft->subfleet);
        $this->assertNotNull($aircraft->subfleet->airline);
        $this->assertEquals('A32X', $aircraft->subfleet->type);
        $this->assertEquals('A320-211', $aircraft->name);
        $this->assertEquals('N309US', $aircraft->registration);
        $this->assertEquals('780DH', $aircraft->fin);
        $this->assertEquals(71500.0, $aircraft->zfw->local(0));
        $this->assertEquals(AircraftStatus::ACTIVE, $aircraft->status);

        // Now try importing the updated file, the status for the aircraft should change
        // to being stored

        $file_path = base_path('tests/data/aircraft-update.csv');
        $status = $this->importSvc->importAircraft($file_path);
        $this->assertCount(1, $status['success']);

        $aircraft = Aircraft::where([
            'registration' => 'N309US',
        ])->first();

        $this->assertEquals(AircraftStatus::STORED, $aircraft->status);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function test_airport_importer(): void
    {
        $file_path = base_path('tests/data/airports.csv');
        $status = $this->importSvc->importAirports($file_path);

        $this->assertCount(2, $status['success']);
        $this->assertCount(1, $status['errors']);

        // See if it imported
        $airport = Airport::where([
            'id' => 'KAUS',
        ])->first();

        $this->assertNotNull($airport);
        $this->assertEquals('KAUS', $airport->id);
        $this->assertEquals('AUS', $airport->iata);
        $this->assertEquals('KAUS', $airport->icao);
        $this->assertEquals('Austin-Bergstrom', $airport->name);
        $this->assertEquals('Austin', $airport->location);
        $this->assertEquals('Texas', $airport->region);
        $this->assertEquals('US', $airport->country);
        $this->assertEquals('America/Chicago', $airport->timezone);
        $this->assertTrue($airport->hub);
        $this->assertEquals('30.1945', $airport->lat);
        $this->assertEquals('-97.6699', $airport->lon);
        $this->assertEquals(0.0, $airport->ground_handling_cost);
        $this->assertEquals(setting('airports.default_jet_a_fuel_cost'), $airport->fuel_jeta_cost);
        $this->assertEquals('Test Note', $airport->notes);

        // See if it imported
        $airport = Airport::where([
            'id' => 'KSFO',
        ])->first();

        $this->assertNotNull($airport);
        $this->assertTrue($airport->hub);
        $this->assertEquals(0.9, $airport->fuel_jeta_cost);
        $this->assertEquals(setting('airports.default_ground_handling_cost'), $airport->ground_handling_cost);
    }

    public function test_airport_importer_invalid_inputs(): void
    {
        $file_path = base_path('tests/data/airports_errors.csv');
        $status = $this->importSvc->importAirports($file_path);

        $this->assertCount(5, $status['success']);
        $this->assertCount(1, $status['errors']);

        // See if it imported
        /** @var Airport $airport */
        $airport = Airport::where([
            'id' => 'CYAV',
        ])->first();

        $this->assertNotNull($airport);
        $this->assertEquals('CYAV', $airport->id);
        $this->assertEquals('', $airport->iata);
        $this->assertEquals('America/Winnipeg', $airport->timezone);
        $this->assertFalse($airport->hub);
        $this->assertEquals('50.0564003', $airport->lat);
        $this->assertEquals('-97.03250122', $airport->lon);
    }

    /**
     * Test importing the subfleets
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function test_subfleet_importer(): void
    {
        $fare_economy = Fare::factory()->create(['code' => 'Y', 'capacity' => 150]);
        $fare_business = Fare::factory()->create(['code' => 'B', 'capacity' => 20]);
        $rank_cpt = Rank::factory()->create(['id' => 99, 'name' => 'cpt']);
        $rank_fo = Rank::factory()->create(['id' => 100, 'name' => 'fo']);
        $airline = Airline::factory()->create(['icao' => 'VMS']);

        $file_path = base_path('tests/data/subfleets.csv');
        $status = $this->importSvc->importSubfleets($file_path);

        $this->assertCount(1, $status['success']);
        $this->assertCount(1, $status['errors']);

        // See if it imported
        $subfleet = Subfleet::where([
            'type' => 'A32X',
        ])->first();

        $this->assertNotNull($subfleet);
        $this->assertEquals($airline->id, $subfleet->id);
        $this->assertEquals('A32X', $subfleet->type);
        $this->assertEquals('Airbus A320', $subfleet->name);

        // get the fares and check the pivot tables and the main tables
        $fares = $subfleet->fares()->get();

        $eco = $fares->firstWhere('code', 'Y');
        $this->assertEquals(null, $eco->pivot->price);
        $this->assertEquals(null, $eco->pivot->capacity);
        $this->assertEquals(null, $eco->pivot->cost);

        $this->assertEquals($fare_economy->price, $eco->price);
        $this->assertEquals($fare_economy->capacity, $eco->capacity);
        $this->assertEquals($fare_economy->cost, $eco->cost);

        $busi = $fares->firstWhere('code', 'B');
        $this->assertEquals($fare_business->price, $busi->price);
        $this->assertEquals($fare_business->capacity, $busi->capacity);
        $this->assertEquals($fare_business->cost, $busi->cost);

        $this->assertEquals('500%', $busi->pivot->price);
        $this->assertEquals(100, $busi->pivot->capacity);
        $this->assertEquals(null, $busi->pivot->cost);

        // get the ranks and check the pivot tables and the main tables
        $ranks = $subfleet->ranks()->get();
        $cpt = $ranks->firstWhere('name', 'cpt');
        $this->assertEquals(null, $cpt->pivot->acars_pay);
        $this->assertEquals(null, $cpt->pivot->manual_pay);

        $this->assertEquals($rank_cpt->acars_pay, $cpt->acars_pay);
        $this->assertEquals($rank_cpt->manual_pay, $cpt->manual_pay);

        $fo = $ranks->firstWhere('name', 'fo');
        $this->assertEquals(200, $fo->pivot->acars_pay);
        $this->assertEquals(100, $fo->pivot->manual_pay);

        $this->assertEquals($rank_fo->acars_pay, $fo->acars_pay);
        $this->assertEquals($rank_fo->manual_pay, $fo->manual_pay);
    }

    public function test_airport_special_chars_importer(): void
    {
        $file_path = base_path('tests/data/airports_special_chars.csv');
        $status = $this->importSvc->importAirports($file_path);

        // See if it imported
        $airport = Airport::where([
            'id' => 'LEMD',
        ])->first();

        $this->assertNotNull($airport);
        $this->assertEquals('Adolfo Suárez Madrid–Barajas Airport', $airport->name);
    }
}
