<?php

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
use App\Services\ImportExport\ExpenseExporter;
use App\Services\ImportExport\FlightExporter;
use App\Services\ImportService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Add some of the basic data needed to properly import the flights.csv file
 */
function insertFlightsScaffoldData(): array
{
    $fareSvc = app(FareService::class);

    $al = [
        'icao' => 'VMS',
        'name' => 'phpVMS Airlines',
    ];

    $airline = Airline::firstWhere(['icao' => 'VMS']) ?? Airline::factory()->create($al);

    $subfleet = Subfleet::factory()->create(['type' => 'A32X']);

    // Add the economy class
    $fare_economy = Fare::factory()->create(['code' => 'Y', 'capacity' => 150]);
    $fareSvc->setForSubfleet($subfleet, $fare_economy);

    $fare_business = Fare::factory()->create(['code' => 'B', 'capacity' => 20]);
    $fareSvc->setForSubfleet($subfleet, $fare_business);

    // Add first class
    $fare_first = Fare::factory()->create(['code' => 'F', 'capacity' => 10]);
    $fareSvc->setForSubfleet($subfleet, $fare_first);

    return [$airline, $subfleet];
}

test('convert string to objects', function () {
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

    $importBaseClass = new ImportExport();

    foreach ($tests as $test) {
        $parsed = $importBaseClass->parseMultiColumnValues($test['input']);
        expect($parsed)->toEqual($test['expected']);
    }
});

test('convert object to string', function () {
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

    $importBaseClass = new ImportExport();

    foreach ($tests as $test) {
        $parsed = $importBaseClass->objectToMultiString($test['input']);
        expect($parsed)->toEqual($test['expected']);
    }
});

test('aircraft exporter', function () {
    $aircraft = Aircraft::factory()->create();

    $exporter = new AircraftExporter();
    $exported = $exporter->export($aircraft);

    expect($exported['iata'])->toEqual($aircraft->iata)
        ->and($exported['icao'])->toEqual($aircraft->icao)
        ->and($exported['name'])->toEqual($aircraft->name)
        ->and($exported['zfw'])->toEqual($aircraft->zfw)
        ->and($exported['subfleet'])->toEqual($aircraft->subfleet->type);

    $importer = app(ImportService::class);
    $exporter = app(ExportService::class);

    $collection = collect([$aircraft]);
    $file = $exporter->exportAircraft($collection);

    $status = $importer->importAircraft($file);
    expect($status['success'])->toHaveCount(1)
        ->and($status['errors'])->toHaveCount(0);
});

test('airport exporter', function () {
    $airport_name = 'Adolfo Suárez Madrid–Barajas Airport';

    $airport = Airport::factory()->create([
        'name' => $airport_name,
    ]);

    $exporter = new AirportExporter();
    $exported = $exporter->export($airport);

    expect($exported['iata'])->toEqual($airport->iata)
        ->and($exported['icao'])->toEqual($airport->icao)
        ->and($exported['name'])->toEqual($airport->name);

    $importer = app(ImportService::class);
    $exporter = app(ExportService::class);
    $file = $exporter->exportAirports(collect([$airport]));
    $status = $importer->importAirports($file);

    expect($status['success'])->toHaveCount(1)
        ->and($status['errors'])->toHaveCount(0);
});

test('flight exporter', function () {
    $fareSvc = app(FareService::class);

    [$airline, $subfleet] = insertFlightsScaffoldData();
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

    expect($exported['days'])->toEqual('27')
        ->and($exported['airline'])->toEqual('VMS')
        ->and($exported['flight_time'])->toEqual($flight->flight_time)
        ->and($exported['flight_type'])->toEqual('J')
        ->and($exported['subfleets'])->toEqual('A32X;B74X')
        ->and($exported['fares'])->toEqual('Y?capacity=100;F')
        ->and($exported['fields'])->toEqual('Departure Gate=4;Arrival Gate=C41');

    $importer = app(ImportService::class);
    $exporter = app(ExportService::class);
    $file = $exporter->exportFlights(collect([$flight]));
    $status = $importer->importFlights($file);
    expect($status['success'])->toHaveCount(1)
        ->and($status['errors'])->toHaveCount(0);
});

test('invalid file import', function () {
    // $this->expectException(ValidationException::class);
    $file_path = base_path('tests/data/aircraft.csv');
    $importer = app(ImportService::class);
    $status = $importer->importAirports($file_path);
    expect($status['errors'])->toHaveCount(2);
});

test('empty cols', function () {
    $file_path = base_path('tests/data/expenses_empty_rows.csv');
    $importer = app(ImportService::class);
    $status = $importer->importExpenses($file_path);
    expect($status['success'])->toHaveCount(8)
        ->and($status['errors'])->toHaveCount(0);
});

test('expense exporter', function () {
    $expense = Expense::factory([
        'airline_id' => Airline::factory()->create()->id,
    ])->create();

    $exporter = new ExpenseExporter();
    $exported = $exporter->export($expense);

    expect($exported['airline'])->toEqual($expense->airline->icao)
        ->and($exported['name'])->toEqual($expense->name)
        ->and($exported['amount'])->toEqual($expense->amount)
        ->and($exported['type'])->toEqual($expense->type);

    $importer = app(ImportService::class);
    $exporter = app(ExportService::class);
    $file = $exporter->exportExpenses(collect([$expense]));
    $status = $importer->importExpenses($file);

    expect($status['success'])->toHaveCount(1)
        ->and($status['errors'])->toHaveCount(0);
});

test('expense importer', function () {
    $airline = Airline::firstWhere(['icao' => 'VMS']) ?? Airline::factory()->create(['icao' => 'VMS']);
    $subfleet = Subfleet::factory()->create(['type' => '744-3X-RB211']);
    $aircraft = Aircraft::factory()->create([
        'subfleet_id'  => $subfleet->id,
        'registration' => '001Z',
    ]);

    $importer = app(ImportService::class);
    $file_path = base_path('tests/data/expenses.csv');
    $status = $importer->importExpenses($file_path);

    expect($status['success'])->toHaveCount(8)
        ->and($status['errors'])->toHaveCount(0);

    $expenses = Expense::all();

    $on_airline = $expenses->firstWhere('name', 'Per-Flight (multiplier, on airline)');
    expect($on_airline->amount)->toEqual(200)
        ->and($on_airline->airline_id)->toEqual($airline->id);

    $pf = $expenses->firstWhere('name', 'Per-Flight (no muliplier)');
    expect($pf->amount)->toEqual(100)
        ->and($pf->type)->toEqual(ExpenseType::FLIGHT);

    $catering = $expenses->firstWhere('name', 'Catering Staff');
    expect($catering->amount)->toEqual(1000)
        ->and($catering->type)->toEqual(ExpenseType::DAILY)
        ->and($catering->ref_model)->toBeInstanceOf(Subfleet::class)
        ->and($catering->ref_model->id)->toEqual($subfleet->id)
        ->and($catering->ref_model_id)->toEqual($subfleet->id);

    $mnt = $expenses->firstWhere('name', 'Maintenance');
    expect($mnt->ref_model)->toBeInstanceOf(Aircraft::class)
        ->and($mnt->ref_model_id)->toEqual($aircraft->id)
        ->and($mnt->ref_model->id)->toEqual($aircraft->id);
});

test('fare importer', function () {
    $file_path = base_path('tests/data/fares.csv');
    $importer = app(ImportService::class);
    $status = $importer->importFares($file_path);

    expect($status['success'])->toHaveCount(4)
        ->and($status['errors'])->toHaveCount(0);

    $fares = Fare::all();

    $y_class = $fares->firstWhere('code', 'Y');
    expect($y_class->name)->toEqual('Economy')
        ->and($y_class->type)->toEqual(FareType::PASSENGER)
        ->and($y_class->price)->toEqual(100)
        ->and($y_class->cost)->toEqual(0)
        ->and($y_class->capacity)->toEqual(200)
        ->and($y_class->active)->toBeTrue()
        ->and($y_class->notes)->toEqual('This is the economy class');

    $b_class = $fares->firstWhere('code', 'B');
    expect($b_class->name)->toEqual('Business')
        ->and($b_class->type)->toEqual(FareType::PASSENGER)
        ->and($b_class->price)->toEqual(500)
        ->and($b_class->cost)->toEqual(250)
        ->and($b_class->capacity)->toEqual(10)
        ->and($b_class->notes)->toEqual('This is business class')
        ->and($b_class->active)->toBeFalse();

    $f_class = $fares->firstWhere('code', 'F');
    expect($f_class->name)->toEqual('First-Class')
        ->and($f_class->type)->toEqual(FareType::PASSENGER)
        ->and($f_class->price)->toEqual(800)
        ->and($f_class->cost)->toEqual(350)
        ->and($f_class->capacity)->toEqual(5)
        ->and($f_class->notes)->toEqual('')
        ->and($f_class->active)->toBeTrue();

    $cargo = $fares->firstWhere('code', 'C');
    expect($cargo->name)->toEqual('Cargo')
        ->and($cargo->type)->toEqual(FareType::CARGO)
        ->and($cargo->price)->toEqual(20)
        ->and($cargo->cost)->toEqual(0)
        ->and($cargo->capacity)->toEqual(10)
        ->and($cargo->notes)->toEqual('')
        ->and($cargo->active)->toBeTrue();
});

test('flight importer', function () {
    [$airline, $subfleet] = insertFlightsScaffoldData();

    $importer = app(ImportService::class);

    $file_path = base_path('tests/data/flights.csv');
    $status = $importer->importFlights($file_path);

    expect($status['success'])->toHaveCount(3)
        ->and($status['errors'])->toHaveCount(1);

    // See if it imported
    /** @var Flight $flight */
    $flight = Flight::where([
        'airline_id'    => $airline->id,
        'flight_number' => '1972',
    ])->first();

    expect($flight)->not->toBeNull()
        ->and($flight->dpt_airport_id)->toEqual('KAUS')
        ->and($flight->arr_airport_id)->toEqual('KJFK')
        ->and($flight->dpt_time)->toEqual('0810 CST')
        ->and($flight->arr_time)->toEqual('1235 EST')
        ->and($flight->level)->toEqual('350')
        ->and($flight->distance->internal())->toEqual(1477)
        ->and($flight->flight_time)->toEqual('207')
        ->and($flight->flight_type)->toEqual(FlightType::SCHED_PAX)
        ->and($flight->route)->toEqual('ILEXY2 ZENZI LFK ELD J29 MEM Q29 JHW J70 STENT J70 MAGIO J70 LVZ LENDY6')
        ->and($flight->notes)->toEqual('Just a flight')
        ->and($flight->active)->toBeTrue()
        ->and($flight->on_day(Days::MONDAY))->toBeTrue()
        ->and($flight->on_day(Days::FRIDAY))->toBeTrue()
        ->and($flight->on_day(Days::TUESDAY))->toBeFalse();

    // Check the custom fields entered
    $fields = FlightFieldValue::where([
        'flight_id' => $flight->id,
    ])->get();

    expect($fields)->toHaveCount(2);
    $dep_gate = $fields->firstWhere('name', 'Departure Gate');
    expect($dep_gate['value'])->toEqual('4');

    $dep_gate = $fields->firstWhere('name', 'Arrival Gate');
    expect($dep_gate['value'])->toEqual('C41');

    // Check the fare class
    $fares = app(FareService::class)->getFareWithOverrides(null, $flight->fares);
    expect($fares)->toHaveCount(3);

    $first = $fares->firstWhere('code', 'Y');
    expect($first->price)->toEqual(300)
        ->and($first->cost)->toEqual(100)
        ->and($first->capacity)->toEqual(130);

    $first = $fares->firstWhere('code', 'F');
    expect($first->price)->toEqual(600)
        ->and($first->cost)->toEqual(400)
        ->and($first->capacity)->toEqual(10);

    // Check the subfleets
    $subfleets = $flight->subfleets;
    expect($subfleets)->toHaveCount(1)
        ->and($subfleets[0]->name)->not->toEqual('A32X');

    $flight = Flight::where([
        'airline_id'    => $airline->id,
        'flight_number' => '999',
    ])->first();
    $subfleets = $flight->subfleets;
    expect($subfleets)->toHaveCount(2)
        ->and($subfleets[1]->type)->toEqual('B737')
        ->and($subfleets[1]->name)->toEqual('B737');
});

test('flight importer empty custom fields', function () {
    [$airline, $subfleet] = insertFlightsScaffoldData();

    $importer = app(ImportService::class);
    $file_path = base_path('tests/data/flights_empty_fields.csv');
    $status = $importer->importFlights($file_path);

    expect($status['success'])->toHaveCount(1)
        ->and($status['errors'])->toHaveCount(0);

    // See if it imported
    $flight = Flight::where([
        'airline_id'    => $airline->id,
        'flight_number' => '1972',
    ])->first();

    expect($flight)->not->toBeNull();

    // Check the custom fields entered
    $fields = FlightFieldValue::where([
        'flight_id' => $flight->id,
    ])->get();

    expect($fields)->toHaveCount(0);
});

test('flight importer core', function () {
    [$airline, $subfleet] = insertFlightsScaffoldData();

    $importer = app(ImportService::class);
    $file_path = base_path('tests/data/flights.csv');
    $status = $importer->importFlights($file_path, 'core');

    expect($status['success'])->toHaveCount(3)
        ->and($status['errors'])->toHaveCount(1);

    // Additional assertions for "core" argument can be added here
});

test('flight importer all', function () {
    [$airline, $subfleet] = insertFlightsScaffoldData();

    $importer = app(ImportService::class);
    $file_path = base_path('tests/data/flights.csv');
    $status = $importer->importFlights($file_path, 'all');

    expect($status['success'])->toHaveCount(3)
        ->and($status['errors'])->toHaveCount(1);

    // Additional assertions for "all" argument can be added here
});

test('aircraft importer', function () {
    Airline::factory()->create();

    $importer = app(ImportService::class);
    // $subfleet = \App\Models\Subfleet::factory()->create(['type' => 'A32X']);
    $file_path = base_path('tests/data/aircraft.csv');
    $status = $importer->importAircraft($file_path);

    expect($status['success'])->toHaveCount(1)
        ->and($status['errors'])->toHaveCount(1);

    // See if it imported
    $aircraft = Aircraft::where([
        'registration' => 'N309US',
    ])->first();

    expect($aircraft)->not->toBeNull()
        ->and($aircraft->hex_code)->not->toBeNull()
        ->and($aircraft->subfleet)->not->toBeNull()
        ->and($aircraft->subfleet->airline)->not->toBeNull()
        ->and($aircraft->subfleet->type)->toEqual('A32X')
        ->and($aircraft->name)->toEqual('A320-211')
        ->and($aircraft->registration)->toEqual('N309US')
        ->and($aircraft->fin)->toEqual('780DH')
        ->and($aircraft->zfw->local(0))->toEqual(71500.0)
        ->and($aircraft->status)->toEqual(AircraftStatus::ACTIVE);

    // Now try importing the updated file, the status for the aircraft should change
    // to being stored
    $file_path = base_path('tests/data/aircraft-update.csv');
    $status = $importer->importAircraft($file_path);
    expect($status['success'])->toHaveCount(1);

    $aircraft = Aircraft::where([
        'registration' => 'N309US',
    ])->first();

    expect($aircraft->status)->toEqual(AircraftStatus::STORED);
});

test('airport importer', function () {
    $importer = app(ImportService::class);
    $file_path = base_path('tests/data/airports.csv');
    $status = $importer->importAirports($file_path);

    expect($status['success'])->toHaveCount(2)
        ->and($status['errors'])->toHaveCount(1);

    // See if it imported
    $airport = Airport::where([
        'id' => 'KAUS',
    ])->first();

    expect($airport)->not->toBeNull()
        ->and($airport->id)->toEqual('KAUS')
        ->and($airport->iata)->toEqual('AUS')
        ->and($airport->icao)->toEqual('KAUS')
        ->and($airport->name)->toEqual('Austin-Bergstrom')
        ->and($airport->location)->toEqual('Austin')
        ->and($airport->region)->toEqual('Texas')
        ->and($airport->country)->toEqual('US')
        ->and($airport->timezone)->toEqual('America/Chicago')
        ->and($airport->hub)->toBeTrue()
        ->and($airport->lat)->toEqual('30.1945')
        ->and($airport->lon)->toEqual('-97.6699')
        ->and($airport->ground_handling_cost)->toEqual(0.0)
        ->and($airport->fuel_jeta_cost)->toEqual(setting('airports.default_jet_a_fuel_cost'))
        ->and($airport->notes)->toEqual('Test Note');

    // See if it imported
    $airport = Airport::where([
        'id' => 'KSFO',
    ])->first();

    expect($airport)->not->toBeNull()
        ->and($airport->hub)->toBeTrue()
        ->and($airport->fuel_jeta_cost)->toEqual(0.9)
        ->and($airport->ground_handling_cost)->toEqual(setting('airports.default_ground_handling_cost'));
});

test('airport importer invalid inputs', function () {
    $importer = app(ImportService::class);
    $file_path = base_path('tests/data/airports_errors.csv');
    $status = $importer->importAirports($file_path);

    expect($status['success'])->toHaveCount(5)
        ->and($status['errors'])->toHaveCount(1);

    // See if it imported
    $airport = Airport::where([
        'id' => 'CYAV',
    ])->first();

    expect($airport)->not->toBeNull()
        ->and($airport->id)->toEqual('CYAV')
        ->and($airport->iata)->toEqual('')
        ->and($airport->timezone)->toEqual('America/Winnipeg')
        ->and($airport->hub)->toBeFalse()
        ->and($airport->lat)->toEqual('50.0564003')
        ->and($airport->lon)->toEqual('-97.03250122');
});

test('subfleet importer', function () {
    $fare_economy = Fare::factory()->create(['code' => 'Y', 'capacity' => 150]);
    $fare_business = Fare::factory()->create(['code' => 'B', 'capacity' => 20]);
    $rank_cpt = Rank::factory()->create(['id' => 99, 'name' => 'cpt']);
    $rank_fo = Rank::factory()->create(['id' => 100, 'name' => 'fo']);
    $airline = Airline::firstWhere(['icao' => 'VMS']) ?? Airline::factory()->create(['icao' => 'VMS']);

    $importer = app(ImportService::class);
    $file_path = base_path('tests/data/subfleets.csv');
    $status = $importer->importSubfleets($file_path);

    expect($status['success'])->toHaveCount(1)
        ->and($status['errors'])->toHaveCount(1);

    // See if it imported
    $subfleet = Subfleet::where([
        'type' => 'A32X',
    ])->first();

    expect($subfleet)->not->toBeNull()
        ->and($subfleet->id)->toEqual($airline->id)
        ->and($subfleet->type)->toEqual('A32X')
        ->and($subfleet->name)->toEqual('Airbus A320');

    // get the fares and check the pivot tables and the main tables
    $fares = $subfleet->fares()->get();

    $eco = $fares->firstWhere('code', 'Y');
    expect($eco->pivot->price)->toEqual(null)
        ->and($eco->pivot->capacity)->toEqual(null)
        ->and($eco->pivot->cost)->toEqual(null)
        ->and($eco->price)->toEqual($fare_economy->price)
        ->and($eco->capacity)->toEqual($fare_economy->capacity)
        ->and($eco->cost)->toEqual($fare_economy->cost);

    $busi = $fares->firstWhere('code', 'B');
    expect($busi->price)->toEqual($fare_business->price)
        ->and($busi->capacity)->toEqual($fare_business->capacity)
        ->and($busi->cost)->toEqual($fare_business->cost)
        ->and($busi->pivot->price)->toEqual('500%')
        ->and($busi->pivot->capacity)->toEqual(100)
        ->and($busi->pivot->cost)->toEqual(null);

    // get the ranks and check the pivot tables and the main tables
    $ranks = $subfleet->ranks()->get();
    $cpt = $ranks->firstWhere('name', 'cpt');
    expect($cpt->pivot->acars_pay)->toEqual(null)
        ->and($cpt->pivot->manual_pay)->toEqual(null)
        ->and($cpt->acars_pay)->toEqual($rank_cpt->acars_pay)
        ->and($cpt->manual_pay)->toEqual($rank_cpt->manual_pay);

    $fo = $ranks->firstWhere('name', 'fo');
    expect($fo->pivot->acars_pay)->toEqual(200)
        ->and($fo->pivot->manual_pay)->toEqual(100)
        ->and($fo->acars_pay)->toEqual($rank_fo->acars_pay)
        ->and($fo->manual_pay)->toEqual($rank_fo->manual_pay);

});

test('airport special chars importer', function () {
    $importer = app(ImportService::class);
    $file_path = base_path('tests/data/airports_special_chars.csv');
    $status = $importer->importAirports($file_path);

    // See if it imported
    $airport = Airport::where([
        'id' => 'LEMD',
    ])->first();

    expect($airport)->not->toBeNull()
        ->and($airport->name)->toEqual('Adolfo Suárez Madrid–Barajas Airport');
});
