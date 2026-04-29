<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Enums\ExpenseType;
use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Expense;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Models\Pirep;
use App\Models\PirepFare;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use App\Queries\JournalTransactionQuery;
use App\Services\BidService;
use App\Services\FareService;
use App\Services\Finance\PirepFinanceService;
use App\Services\Finance\RecurringFinanceService;
use App\Services\FinanceService;
use App\Services\FleetService;
use App\Services\JournalService;
use App\Services\PirepService;
use App\Support\Math;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    loadYamlIntoDb('fleet');
});

/**
 * Create a user and a PIREP, that has all of the data filled out
 * so that we can test all of the disparate parts of the finances
 */
function createFullPirep(?Airline $airline = null): array
{
    /**
     * Setup tests
     */
    $subfleet = Subfleet::factory()->hasAircraft(2)->create([
        'cost_block_hour'            => 10,
        'ground_handling_multiplier' => 100,
    ]);

    $rank = Rank::factory()->hasAttached($subfleet)->create([
        'acars_base_pay_rate' => 10,
    ]);

    app(FleetService::class)->addSubfleetToRank($subfleet, $rank);

    $dpt_apt = Airport::factory()->create([
        'ground_handling_cost' => 10,
        'fuel_jeta_cost'       => 10,
    ]);

    $arr_apt = Airport::factory()->create([
        'ground_handling_cost' => 10,
        'fuel_jeta_cost'       => 10,
    ]);

    $u = [
        'rank_id' => $rank->id,
    ];

    if ($airline instanceof Airline) {
        $u['airline_id'] = $airline->id;
    }

    $user = User::factory()->create($u);
    $user->airline->initJournal('USD');

    $flight = Flight::factory()->create([
        'airline_id'     => $user->airline_id,
        'dpt_airport_id' => $dpt_apt->icao,
        'arr_airport_id' => $arr_apt->icao,
    ]);

    $pirep = Pirep::factory()->create([
        'flight_number'  => $flight->flight_number,
        'flight_type'    => FlightType::SCHED_PAX,
        'route_code'     => $flight->route_code,
        'route_leg'      => $flight->route_leg,
        'dpt_airport_id' => $dpt_apt->id,
        'arr_airport_id' => $arr_apt->id,
        'user_id'        => $user->id,
        'airline_id'     => $user->airline_id,
        'aircraft_id'    => $subfleet['aircraft']->random(),
        'flight_id'      => $flight->id,
        'source'         => PirepSource::ACARS,
        'flight_time'    => 120,
        'block_fuel'     => 10,
        'fuel_used'      => 9,
    ]);

    /**
     * Add fares to the subfleet, and then add the fares
     * to the PIREP when it's saved, and set the capacity
     */
    $fares = Fare::factory()->count(2)->create([
        'price'    => 100,
        'cost'     => 50,
        'capacity' => 10,
    ]);

    // Add one weird id to test the pluck
    $fares[] = Fare::factory()->create([
        'id'       => 200,
        'price'    => 100,
        'cost'     => 50,
        'capacity' => 10,
    ]);

    foreach ($fares as $fare) {
        app(FareService::class)->setForSubfleet($subfleet, $fare);
    }

    // Add an expense
    Expense::factory()->create([
        'airline_id' => null,
        'amount'     => 100,
    ]);

    // Add a subfleet expense
    Expense::factory()->create([
        'ref_model_type' => Subfleet::class,
        'ref_model_id'   => $subfleet->id,
        'amount'         => 200,
    ]);

    // Add expenses for airports
    Expense::factory()->create([
        'ref_model_type' => Airport::class,
        'ref_model_id'   => $dpt_apt->id,
        'amount'         => 50,
    ]);

    Expense::factory()->create([
        'ref_model_type' => Airport::class,
        'ref_model_id'   => $arr_apt->id,
        'amount'         => 100,
    ]);

    $pirep = app(PirepService::class)->create($pirep, []);

    return [$user, $pirep, $fares];
}

test('flight fares over api', function () {
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    $user = User::factory()->create();
    apiAs($user);

    $flight = Flight::factory()->create();

    $subfleet = Subfleet::factory()->create();
    app(FleetService::class)->addSubfleetToFlight($subfleet, $flight);

    /**
     * Set a base fare
     * Then override on multiple layers - subfleet modifies the cost, the flight modifies
     * the price. This should then all be reflected as we go down the chain. This is
     * mostly for the output side
     */
    $fare = Fare::factory()->create([
        'price'    => 10,
        'cost'     => 20,
        'capacity' => 100,
    ]);

    $fareSvc = app(FareService::class);

    $fareSvc->setForSubfleet($subfleet, $fare, [
        'capacity' => 200,
    ]);

    $fareSvc->setForFlight($flight, $fare, [
        'price' => 50,
    ]);

    $flight = $fareSvc->getReconciledFaresForFlight($flight);

    expect($flight->subfleets[0]->fares[0]->price)->toEqual(50)
        ->and($flight->subfleets[0]->fares[0]->capacity)->toEqual(200)
        ->and($flight->subfleets[0]->fares[0]->cost)->toEqual(20);

    //
    // set an override now (but on the flight)
    //
    $req = $this->get('/api/flights/'.$flight->id);
    $req->assertStatus(200);

    $body = $req->json()['data'];
    expect($body['id'])->toEqual($flight->id)
        ->and($body['subfleets'])->toHaveCount(1)
        ->and($body['subfleets'][0]['fares'][0]['price'])->toEqual(50)
        ->and($body['subfleets'][0]['fares'][0]['capacity'])->toEqual(200)
        ->and($body['subfleets'][0]['fares'][0]['cost'])->toEqual(20);

    // Fares, etc, should be adjusted, per-subfleet

    $req = $this->get('/api/flights/search?flight_id='.$flight->id);
    $req->assertStatus(200);

    $body = $req->json()['data'][0];
    expect($body['id'])->toEqual($flight->id)
        ->and($body['subfleets'])->toHaveCount(1)
        ->and($body['subfleets'][0]['fares'][0]['price'])->toEqual(50)
        ->and($body['subfleets'][0]['fares'][0]['capacity'])->toEqual(200)
        ->and($body['subfleets'][0]['fares'][0]['cost'])->toEqual(20);

    // Fares, etc, should be adjusted, per-subfleet
});

test('flight fares over api on user bids', function () {
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    $bidSvc = app(BidService::class);
    $fleetSvc = app(FleetService::class);
    $fareSvc = app(FareService::class);

    $user = User::factory()->create();
    apiAs($user);

    $flight = Flight::factory()->create();

    $subfleet = Subfleet::factory()->create();
    $fleetSvc->addSubfleetToFlight($subfleet, $flight);

    /** @var Fare $fare */
    $fare = Fare::factory()->create();

    $fareSvc->setForFlight($flight, $fare);

    //
    // set an override now (but on the flight)
    //
    $fareSvc->setForFlight($flight, $fare, ['price' => 50]);
    $bid = $bidSvc->addBid($flight, $user);

    $req = $this->get('/api/user/bids');
    $req->assertStatus(200);

    $body = $req->json()['data'];
    expect($body[0]['flight_id'])->toEqual($flight->id)
        ->and($body[0]['flight']['subfleets'])->toHaveCount(1)
        ->and($body[0]['flight']['fares'][0]['price'])->toEqual(50)
        ->and($body[0]['flight']['fares'][0]['capacity'])->toEqual($fare->capacity);
});

test('subfleet fares over api', function () {
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);

    /**
     * Add a user and flights
     */
    $user = User::factory()->create();
    apiAs($user);

    $subfleet = Subfleet::factory()->create();

    $flight = Flight::factory()->hasAttached($subfleet)->create([
        'airline_id' => $user->airline_id,
    ]);

    $fareSvc = app(FareService::class);

    $fare = Fare::factory()->create();
    $fareSvc->setForSubfleet($flight->subfleets->first(), $fare, ['price' => 50]);

    // Get from API
    $req = $this->get('/api/flights/'.$flight->id);
    $req->assertStatus(200);

    $body = $req->json()['data'];
    expect($body['id'])->toEqual($flight->id)
        ->and($body['subfleets'])->toHaveCount(1)
        ->and($body['subfleets'][0]['fares'][0]['price'])->toEqual(50)
        ->and($body['subfleets'][0]['fares'][0]['capacity'])->toEqual($fare->capacity);
});

test('flight fare override as percent', function () {
    $flight = Flight::factory()->create();
    $fare = Fare::factory()->create();

    $fareSvc = app(FareService::class);

    // Subfleet needs to be attached to a flight
    $subfleet = Subfleet::factory()->create();
    app(FleetService::class)->addSubfleetToFlight($subfleet, $flight);

    $percent_incr = '120%';
    $percent_decr = '80%';
    $percent_200 = '200%';

    $new_price = Math::getPercent($fare->price, $percent_incr);
    $new_cost = Math::getPercent($fare->cost, $percent_decr);
    $new_capacity = Math::getPercent($fare->capacity, $percent_200);

    $fareSvc->setForFlight($flight, $fare, [
        'price'    => $percent_incr,
        'cost'     => $percent_decr,
        'capacity' => $percent_200,
    ]);

    // A subfleet is required to be passed in
    $ac_fares = $fareSvc->getAllFares($flight, $subfleet);

    expect($ac_fares)->toHaveCount(1)
        ->and($ac_fares[0]->price)->toEqual($new_price)
        ->and($ac_fares[0]->cost)->toEqual($new_cost)
        ->and($ac_fares[0]->capacity)->toEqual($new_capacity);
});

test('subfleet fares no override', function () {
    $subfleet = Subfleet::factory()->create();
    $fare = Fare::factory()->create();

    $fareSvc = app(FareService::class);

    $fareSvc->setForSubfleet($subfleet, $fare);
    $subfleet_fares = $fareSvc->getForSubfleet($subfleet);

    expect($subfleet_fares)->toHaveCount(1)
        ->and($subfleet_fares->get(0)->price)->toEqual($fare->price)
        ->and($subfleet_fares->get(0)->capacity)->toEqual($fare->capacity);

    //
    // set an override now
    //
    $fareSvc->setForSubfleet($subfleet, $fare, [
        'price'    => 50,
        'capacity' => 400,
    ]);

    // look for them again
    $subfleet_fares = $fareSvc->getForSubfleet($subfleet);

    expect($subfleet_fares)->toHaveCount(1)
        ->and($subfleet_fares[0]->price)->toEqual(50)
        ->and($subfleet_fares[0]->capacity)->toEqual(400);

    // delete
    $fareSvc->delFareFromSubfleet($subfleet, $fare);
    expect($fareSvc->getForSubfleet($subfleet))->toHaveCount(0);
});

test('subfleet fares override', function () {
    $subfleet = Subfleet::factory()->create();
    $fare = Fare::factory()->create();

    $fareSvc = app(FareService::class);

    $fareSvc->setForSubfleet($subfleet, $fare, [
        'price'    => 50,
        'capacity' => 400,
    ]);

    $ac_fares = $fareSvc->getForSubfleet($subfleet);

    expect($ac_fares)->toHaveCount(1)
        ->and($ac_fares[0]->price)->toEqual(50)
        ->and($ac_fares[0]->capacity)->toEqual(400);

    //
    // update the override to a different amount and make sure it updates
    //
    $fareSvc->setForSubfleet($subfleet, $fare, [
        'price'    => 150,
        'capacity' => 50,
    ]);

    $ac_fares = $fareSvc->getForSubfleet($subfleet);

    expect($ac_fares)->toHaveCount(1)
        ->and($ac_fares[0]->price)->toEqual(150)
        ->and($ac_fares[0]->capacity)->toEqual(50);

    // delete
    $fareSvc->delFareFromSubfleet($subfleet, $fare);
    expect($fareSvc->getForSubfleet($subfleet))->toHaveCount(0);
});

test('subfleet fare override as percent', function () {
    $subfleet = Subfleet::factory()->create();
    $fare = Fare::factory()->create();

    $percent_incr = '20%';
    $percent_decr = '-20%';
    $percent_200 = '200%';

    $new_price = Math::getPercent($fare->price, $percent_incr);
    $new_cost = Math::getPercent($fare->cost, $percent_decr);
    $new_capacity = Math::getPercent($fare->capacity, $percent_200);

    $fareSvc = app(FareService::class);

    $fareSvc->setForSubfleet($subfleet, $fare, [
        'price'    => $percent_incr,
        'cost'     => $percent_decr,
        'capacity' => $percent_200,
    ]);

    $ac_fares = $fareSvc->getForSubfleet($subfleet);

    expect($ac_fares)->toHaveCount(1)
        ->and($ac_fares[0]->price)->toEqual($new_price)
        ->and($ac_fares[0]->cost)->toEqual($new_cost)
        ->and($ac_fares[0]->capacity)->toEqual($new_capacity);
});

test('get fares with overrides', function () {
    $flight = Flight::factory()->create();
    $subfleet = Subfleet::factory()->create();
    [$fare1, $fare2, $fare3, $fare4] = Fare::factory()->count(4)->create();

    $fareSvc = app(FareService::class);

    // add to the subfleet, and just override one of them
    $fareSvc->setForSubfleet($subfleet, $fare1);
    $fareSvc->setForSubfleet($subfleet, $fare2, [
        'price'    => 100,
        'cost'     => 50,
        'capacity' => 25,
    ]);

    $fareSvc->setForSubfleet($subfleet, $fare3);

    // Now set the last one to the flight and then override stuff
    $fareSvc->setForFlight($flight, $fare3, [
        'price' => '300%',
        'cost'  => 250,
    ]);

    $fare3_price = Math::getPercent($fare3->price, 300);

    // Assign another one to the flight, that's not on the subfleet
    // This one should NOT be returned in the list of fares
    $fareSvc->setForFlight($flight, $fare4);

    $fares = $fareSvc->getAllFares($flight, $subfleet);
    expect($fares)->toHaveCount(3);

    foreach ($fares as $fare) {
        switch ($fare->id) {
            case $fare1->id:
                expect($fare1->price)->toEqual($fare->price)
                    ->and($fare1->cost)->toEqual($fare->cost)
                    ->and($fare1->capacity)->toEqual($fare->capacity);
                break;

            case $fare2->id:
                expect(100)->toEqual($fare->price)
                    ->and(50)->toEqual($fare->cost)
                    ->and(25)->toEqual($fare->capacity);
                break;

            case $fare3->id:
                expect($fare3_price)->toEqual($fare->price)
                    ->and(250)->toEqual($fare->cost)
                    ->and($fare3->capacity)->toEqual($fare->capacity);
                break;
        }
    }
});

test('get fares no flight overrides', function () {
    $subfleet = Subfleet::factory()->create();
    [$fare1, $fare2, $fare3] = Fare::factory()->count(3)->create();

    $fareSvc = app(FareService::class);

    // add to the subfleet, and just override one of them
    $fareSvc->setForSubfleet($subfleet, $fare1);
    $fareSvc->setForSubfleet($subfleet, $fare2, [
        'price'    => 100,
        'cost'     => 50,
        'capacity' => 25,
    ]);

    $fareSvc->setForSubfleet($subfleet, $fare3);

    $fares = $fareSvc->getAllFares(null, $subfleet);
    expect($fares)->toHaveCount(3);

    foreach ($fares as $fare) {
        switch ($fare->id) {
            case $fare1->id:
                expect($fare1->price)->toEqual($fare->price)
                    ->and($fare1->cost)->toEqual($fare->cost)
                    ->and($fare1->capacity)->toEqual($fare->capacity);
                break;

            case $fare2->id:
                expect(100)->toEqual($fare->price)
                    ->and(50)->toEqual($fare->cost)
                    ->and(25)->toEqual($fare->capacity);
                break;

            case $fare3->id:
                expect($fare3->price)->toEqual($fare->price)
                    ->and($fare3->cost)->toEqual($fare->cost)
                    ->and($fare3->capacity)->toEqual($fare->capacity);
                break;
        }
    }
});

test('get pilot pay no override', function () {
    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();
    app(FleetService::class)->addSubfleetToRank($subfleet, $rank);

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);

    $pirep = Pirep::factory()->create([
        'user_id'     => $user->id,
        'aircraft_id' => $subfleet->aircraft->random(),
        'source'      => PirepSource::ACARS,
    ]);

    $rate = app(PirepFinanceService::class)->getPilotPayRateForPirep($pirep);
    expect($rate)->toEqual($rank->acars_base_pay_rate);
});

test('get pilot pay with override', function () {
    $acars_pay_rate = 100;

    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    $fleetSvc = app(FleetService::class);
    $financeSvc = app(PirepFinanceService::class);

    $fleetSvc->addSubfleetToRank($subfleet, $rank, [
        'acars_pay' => $acars_pay_rate,
    ]);

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);

    $pirep_acars = Pirep::factory()->create([
        'user_id'     => $user->id,
        'aircraft_id' => $subfleet->aircraft->random(),
        'source'      => PirepSource::ACARS,
    ]);

    $rate = $financeSvc->getPilotPayRateForPirep($pirep_acars);
    expect($rate)->toEqual($acars_pay_rate);

    // Change to a percentage
    $manual_pay_rate = '50%';
    $manual_pay_adjusted = Math::getPercent(
        $rank->manual_base_pay_rate,
        $manual_pay_rate
    );

    $fleetSvc->addSubfleetToRank($subfleet, $rank, [
        'manual_pay' => $manual_pay_rate,
    ]);

    $pirep_manual = Pirep::factory()->create([
        'user_id'     => $user->id,
        'aircraft_id' => $subfleet['aircraft']->random(),
        'source'      => PirepSource::MANUAL,
    ]);

    $rate = $financeSvc->getPilotPayRateForPirep($pirep_manual);
    expect($rate)->toEqual($manual_pay_adjusted);

    // And make sure the original acars override still works
    $rate = $financeSvc->getPilotPayRateForPirep($pirep_acars);
    expect($rate)->toEqual($acars_pay_rate);
});

test('get pirep pilot pay', function () {
    $acars_pay_rate = 100;

    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();

    app(FleetService::class)->addSubfleetToRank($subfleet, $rank, [
        'acars_pay' => $acars_pay_rate,
    ]);

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);

    $pirep_acars = Pirep::factory()->create([
        'user_id'     => $user->id,
        'aircraft_id' => $subfleet->aircraft->random(),
        'source'      => PirepSource::ACARS,
        'flight_time' => 60,
    ]);

    $financeSvc = app(PirepFinanceService::class);

    $payment = $financeSvc->getPilotPay($pirep_acars);
    expect($payment->getValue())->toEqual(100);

    $pirep_acars = Pirep::factory()->create([
        'user_id'     => $user->id,
        'aircraft_id' => $subfleet->aircraft->random(),
        'source'      => PirepSource::ACARS,
        'flight_time' => 90,
    ]);

    $payment = $financeSvc->getPilotPay($pirep_acars);
    expect(150)->toEqual($payment->getValue());
});

test('get pirep pilot pay with fixed price', function () {
    $acars_pay_rate = 100;

    $subfleet = Subfleet::factory()->hasAircraft(2)->create();
    $rank = Rank::factory()->hasAttached($subfleet)->create();
    app(FleetService::class)->addSubfleetToRank($subfleet, $rank, [
        'acars_pay' => $acars_pay_rate,
    ]);

    $user = User::factory()->create([
        'rank_id' => $rank->id,
    ]);

    $flight = Flight::factory()->create([
        'airline_id' => $user->airline_id,
        'pilot_pay'  => 1000,
    ]);

    $pirep_acars = Pirep::factory()->create([
        'user_id'     => $user->id,
        'aircraft_id' => $subfleet->aircraft->random(),
        'source'      => PirepSource::ACARS,
        'flight_id'   => $flight->id,
        'flight_time' => 60,
    ]);

    $financeSvc = app(PirepFinanceService::class);

    $payment = $financeSvc->getPilotPay($pirep_acars);
    expect($payment->getValue())->toEqual(1000);

    $pirep_acars = Pirep::factory()->create([
        'user_id'     => $user->id,
        'aircraft_id' => $subfleet->aircraft->random(),
        'source'      => PirepSource::ACARS,
        'flight_time' => 90,
    ]);

    $payment = $financeSvc->getPilotPay($pirep_acars);
    expect(150)->toEqual($payment->getValue());
});

test('journal operations', function () {
    $journalSvc = app(JournalService::class);
    $journalQuery = app(JournalTransactionQuery::class);

    $user = User::factory()->create();
    $journal = Journal::factory()->create();

    $journalSvc->post(
        $journal,
        Money::createFromAmount(100.5),
        null,
        $user
    );

    $journal->refresh();
    $balance = $journal->getCurrentBalance();
    expect($balance->getValue())->toEqual(100.5)
        ->and($journal->balance->getValue())->toEqual(100.5);

    // add another transaction
    $journalSvc->post(
        $journal,
        Money::createFromAmount(24.5),
        null,
        $user
    );

    $journal->refresh();
    $balance = $journal->getCurrentBalance();
    expect($balance->getValue())->toEqual(125)
        ->and($journal->balance->getValue())->toEqual(125);

    // debit an amount
    $journalSvc->post(
        $journal,
        null,
        Money::createFromAmount(25),
        $user
    );

    $journal->refresh();
    $balance = $journal->getCurrentBalance();
    expect($balance->getValue())->toEqual(100)
        ->and($journal->balance->getValue())->toEqual(100);

    // find all transactions
    $transactions = $journalQuery->build($user);

    expect($transactions['transactions'])->toHaveCount(3)
        ->and($transactions['credits']->getValue())->toEqual(125)
        ->and($transactions['debits']->getValue())->toEqual(25);
});

test('pirep fares', function () {
    [$user, $pirep, $fares] = createFullPirep();

    // Override the fares
    $fare_counts = [];
    foreach ($fares as $fare) {
        $fare_counts[] = new PirepFare([
            'fare_id' => $fare->id,
            'count'   => round($fare->capacity / 2),
        ]);
    }

    $fareSvc = app(FareService::class);
    $financeSvc = app(PirepFinanceService::class);

    $fareSvc->saveToPirep($pirep, $fare_counts);
    $all_fares = $financeSvc->getFaresForPirep($pirep);

    expect($all_fares)->toHaveCount(3);
    $fare_counts = collect($fare_counts);
    foreach ($all_fares as $fare) {
        // $set_fare = $fare_counts->where('fare_id', $fare->id)->first();
        expect($fare->count)->toEqual($fare['count'])
            ->and($fare['price'])->not->toBeEmpty()
            ->and($fare['cost'])->not->toBeEmpty()
            ->and($fare['capacity'])->not->toBeEmpty();
    }
});

test('pirep expenses', function () {
    $financeSvc = app(FinanceService::class);

    $airline = Airline::factory()->create();

    $airline2 = Airline::factory()->create();

    Expense::factory()->create([
        'airline_id' => $airline->id,
    ]);

    Expense::factory()->create([
        'airline_id' => $airline2->id,
    ]);

    Expense::factory()->create([
        'airline_id' => null,
    ]);

    $expenses = $financeSvc->getExpensesForType(
        ExpenseType::FLIGHT,
        $airline->id,
        Expense::class
    );

    expect($expenses)->toHaveCount(2);

    $found = $expenses->where('airline_id', null);
    expect($found)->toHaveCount(1);

    $found = $expenses->where('airline_id', $airline->id);
    expect($found)->toHaveCount(1);

    $found = $expenses->where('airline_id', $airline2->id);
    expect($found)->toHaveCount(0);

    /*
     * Test the subfleet class
     */
    $subfleet = Subfleet::factory()->create();
    Expense::factory()->create([
        'airline_id'     => null,
        'ref_model_type' => Subfleet::class,
        'ref_model_id'   => $subfleet->id,
    ]);

    $expenses = $financeSvc->getExpensesForType(
        ExpenseType::FLIGHT,
        $airline->id,
        $subfleet
    );

    expect($expenses)->toHaveCount(1);

    $expense = $expenses->random();
    expect($expense->ref_model)->toBeInstanceOf(Subfleet::class)
        ->and($expense->ref_model_id)->toEqual($expense->ref_model->id);
});

test('airport expenses', function () {
    $financeSvc = app(FinanceService::class);

    $apt1 = Airport::factory()->create();
    $apt2 = Airport::factory()->create();
    $apt3 = Airport::factory()->create();

    Expense::factory()->create([
        'airline_id'     => null,
        'ref_model_type' => Airport::class,
        'ref_model_id'   => $apt1->id,
    ]);

    Expense::factory()->create([
        'airline_id'     => null,
        'ref_model_type' => Airport::class,
        'ref_model_id'   => $apt2->id,
    ]);

    Expense::factory()->create([
        'airline_id'     => null,
        'ref_model_type' => Airport::class,
        'ref_model_id'   => $apt3->id,
    ]);

    $expenses = $financeSvc->getExpensesForType(
        ExpenseType::FLIGHT,
        null,
        Airport::class
    );

    expect($expenses)->toHaveCount(3);
});

test('pirep finances', function () {
    $journalQuery = app(JournalTransactionQuery::class);
    $fareSvc = app(FareService::class);
    $pirepSvc = app(PirepService::class);

    [$user, $pirep, $fares] = createFullPirep();
    $user->airline->initJournal(setting('units.currency', 'USD'));

    // Override the fares
    $fareTotal = 0;
    $fareCounts = [];
    foreach ($fares as $fare) {
        $fareCounts[] = new PirepFare([
            'fare_id' => $fare->id,
            'count'   => 10,
        ]);

        $fareTotal += $fare->price * 100;
    }

    $fareSvc->saveToPirep($pirep, $fareCounts);

    // This should process all of the
    $pirep = $pirepSvc->accept($pirep);

    $transactions = $journalQuery->build($pirep);

    /** @var Money $credits */
    $credits = $transactions['credits'];

    /** @var Money $credits */
    $debits = $transactions['debits'];

    // $this->assertCount(9, $transactions['transactions']);
    expect($credits->getValue())->toEqual(3020);
    expect($debits->getValue())->toEqual(2050.4);

    // Check that all the different transaction types are there
    // test by the different groups that exist
    $transaction_tags = [
        'fuel'            => 1,
        'airport'         => 1,
        'expense'         => 1,
        'subfleet'        => 2,
        'fare'            => 3,
        'ground_handling' => 2,
        'pilot_pay'       => 2, // debit on the airline, credit to the pilot
    ];

    foreach ($transaction_tags as $type => $count) {
        $find = $transactions['transactions']->where('tags', $type);
        expect($find->count())->toEqual($count);
    }
});

test('pirep finances specific expense', function () {
    $journalQuery = app(JournalTransactionQuery::class);
    $fareSvc = app(FareService::class);
    $pirepSvc = app(PirepService::class);

    // Add an expense that's only for a cargo flight
    Expense::factory()->create([
        'airline_id'  => null,
        'amount'      => 100,
        'flight_type' => FlightType::SCHED_CARGO,
    ]);

    [$user, $pirep, $fares] = createFullPirep();
    $user->airline->initJournal(setting('units.currency', 'USD'));

    // Override the fares
    $fare_counts = [];
    foreach ($fares as $fare) {
        $fare_counts[] = new PirepFare([
            'fare_id' => $fare->id,
            'count'   => 10,
        ]);
    }

    $fareSvc->saveToPirep($pirep, $fare_counts);

    // This should process all of the
    $pirep = $pirepSvc->accept($pirep);

    $transactions = $journalQuery->build($pirep);

    //        $this->assertCount(9, $transactions['transactions']);
    expect($transactions['credits']->getValue())->toEqual(3020)
        ->and($transactions['debits']->getValue())->toEqual(2050.4);

    // Retrieve data from the pirep fares to make sure all of the fields were saved
    $saved_fares = PirepFare::where('pirep_id', $pirep->id)->get();
    expect($saved_fares)->toHaveCount(3);

    /** @var PirepFare $f */
    foreach ($saved_fares as $f) {
        /** @var Fare $original_fare */
        $original_fare = $fares->where('code', $f->code)->first();

        expect($f->price)->toEqual($original_fare->price)
            ->and($f->cost)->toEqual($original_fare->cost)
            ->and($f->capacity)->toEqual($original_fare->capacity)
            ->and($f->type)->toEqual($original_fare->type);
    }

    // Check that all the different transaction types are there
    // test by the different groups that exist
    $transaction_tags = [
        'fuel'            => 1,
        'airport'         => 1,
        'expense'         => 1,
        'subfleet'        => 2,
        'fare'            => 3,
        'ground_handling' => 2,
        'pilot_pay'       => 2, // debit on the airline, credit to the pilot
    ];

    foreach ($transaction_tags as $type => $count) {
        $find = $transactions['transactions']->where('tags', $type);
        expect($find->count())->toEqual($count);
    }

    // Add a new PIREP;
    $pirep2 = Pirep::factory()->create([
        'flight_number'  => 100,
        'flight_type'    => FlightType::SCHED_CARGO,
        'dpt_airport_id' => $pirep->dpt_airport_id,
        'arr_airport_id' => $pirep->arr_airport_id,
        'user_id'        => $user->id,
        'airline_id'     => $user->airline_id,
        'aircraft_id'    => $pirep->aircraft_id,
        'source'         => PirepSource::ACARS,
        'flight_time'    => 120,
        'block_fuel'     => 10,
        'fuel_used'      => 9,
    ]);

    // Override the fares
    $fare_counts = [];
    foreach ($fares as $fare) {
        $fare_counts[] = new PirepFare([
            'fare_id' => $fare->id,
            'count'   => 10,
        ]);
    }

    $fareSvc->saveToPirep($pirep2, $fare_counts);
    $pirep2 = $pirepSvc->accept($pirep2);

    // Retrieve data from the pirep fares to make sure all of the fields were saved
    $saved_fares = PirepFare::where('pirep_id', $pirep2->id)->get();
    expect($saved_fares)->toHaveCount(3);

    $transactions = $journalQuery->build($pirep2);
    expect($transactions['credits']->getValue())->toEqual(3020)
        ->and($transactions['debits']->getValue())->toEqual(2150.4);

    // Check that all the different transaction types are there
    // test by the different groups that exist
    $transaction_tags = [
        'fuel'            => 1,
        'airport'         => 1,
        'expense'         => 2,
        'subfleet'        => 2,
        'fare'            => 3,
        'ground_handling' => 2,
        'pilot_pay'       => 2, // debit on the airline, credit to the pilot
    ];

    foreach ($transaction_tags as $type => $count) {
        $find = $transactions['transactions']->where('tags', $type);
        expect($find->count())->toEqual($count);
    }
});

test('pirep finances expenses multi airline', function () {
    $airline = Airline::factory()->create();

    /** @var JournalTransactionQuery $journalQuery */
    $journalQuery = app(JournalTransactionQuery::class);

    // Add an expense that's only for a cargo flight
    Expense::factory()->create(
        [
            'airline_id'  => null,
            'amount'      => 100,
            'flight_type' => FlightType::SCHED_CARGO,
        ]
    );

    [$user, $pirep, $fares] = createFullPirep();
    $user->airline->initJournal(setting('units.currency', 'USD'));

    Expense::factory()->create(
        [
            'airline_id'  => $user->airline->id,
            'amount'      => 100,
            'flight_type' => FlightType::SCHED_CARGO,
        ]
    );

    Expense::factory()->create(
        [
            'airline_id'  => $airline->id,
            'amount'      => 100,
            'flight_type' => FlightType::SCHED_CARGO,
        ]
    );

    // There shouldn't be an expense from this subfleet
    $subfleet = Subfleet::factory()->create();
    Expense::factory()->create([
        'airline_id'     => null,
        'amount'         => 100,
        'ref_model_type' => Subfleet::class,
        'ref_model_id'   => $subfleet->id,
    ]);

    // Override the fares
    $fare_counts = [];
    foreach ($fares as $fare) {
        $fare_counts[] = new PirepFare([
            'fare_id' => $fare->id,
            'price'   => $fare->price,
            'count'   => 10,
        ]);
    }

    $fareSvc = app(FareService::class);
    $pirepSvc = app(PirepService::class);

    $fareSvc->saveToPirep($pirep, $fare_counts);

    // This should process all of the
    $pirep = $pirepSvc->accept($pirep);

    $transactions = $journalQuery->build($pirep);

    /** @var JournalTransaction $transaction */
    /*foreach ($transactions['transactions'] as $transaction) {
          echo $transaction->memo."-"."\n";
      }*/
    // Check that all the different transaction types are there
    // test by the different groups that exist
    $transaction_tags = [
        'fuel'            => 1,
        'airport'         => 1,
        'expense'         => 1,
        'subfleet'        => 2,
        'fare'            => 3,
        'ground_handling' => 2,
        'pilot_pay'       => 2, // debit on the airline, credit to the pilot
    ];

    foreach ($transaction_tags as $type => $count) {
        $find = $transactions['transactions']->where('tags', $type);
        expect($find->count())->toEqual($count, $type);
    }

    //        $this->assertCount(9, $transactions['transactions']);
    expect($transactions['credits']->getValue())->toEqual(3020)
        ->and($transactions['debits']->getValue())->toEqual(2050.4);
});

test('pirep expenses nightly', function () {
    $journalQuery = app(JournalTransactionQuery::class);
    $pirepSvc = app(PirepService::class);

    $airline = Airline::factory()->create();
    $airline->initJournal(setting('units.currency', 'USD'));

    $airline2 = Airline::factory()->create();
    $airline2->initJournal(setting('units.currency', 'USD'));

    Expense::factory()->create([
        'airline_id' => null,
        'type'       => ExpenseType::DAILY,
    ]);

    Expense::factory()->create([
        'airline_id' => $airline->id,
        'type'       => ExpenseType::DAILY,
    ]);

    Expense::factory()->create([
        'airline_id' => $airline2->id,
        'type'       => ExpenseType::DAILY,
    ]);

    Expense::factory()->create([
        'airline_id' => null,
        'type'       => ExpenseType::DAILY,
    ]);

    /*
     * Test the subfleet class
     */
    $subfleet = Subfleet::factory()->create();
    $subfleet2 = Subfleet::factory()->create();

    $financeSvc = app(FinanceService::class);

    $exp = Expense::factory()->make([
        'airline_id' => null,
        'type'       => ExpenseType::DAILY,
    ]);

    $financeSvc->addExpense($exp->toArray(), $subfleet);

    [$user, $pirep, $fares] = createFullPirep();
    $pirep = $pirepSvc->accept($pirep);

    // Sanity-check pre-state: no DAILY expense transactions should exist
    // on either airline's journal before processExpenses runs. Count via
    // journal_id directly because processExpenses posts with
    // ref_model = Expense, not = Airline.
    $airline->refresh();
    $airline2->refresh();
    expect($airline->journal->transactions()->count())->toBe(0)
        ->and($airline2->journal->transactions()->count())->toBe(0);

    /** @var RecurringFinanceService $recurringFService */
    $recurringFService = app(RecurringFinanceService::class);
    $recurringFService->processExpenses(ExpenseType::DAILY);

    // After processExpenses, each airline's journal should have received
    // at least one DAILY expense (their own + any null-airline ones,
    // which apply to every airline).
    expect($airline->journal->transactions()->count())->toBeGreaterThan(0)
        ->and($airline2->journal->transactions()->count())->toBeGreaterThan(0);
});

test('daily expenses are applied', function () {
    Carbon::setTestNow(now());

    /** @var Airline $airline */
    $airline = Airline::factory()->create();
    $airline->initJournal(setting('units.currency', 'USD'));
    $airline->refresh();

    /** @var Airline $airline2 */
    $airline2 = Airline::factory()->create();
    $airline2->initJournal(setting('units.currency', 'USD'));
    $airline2->refresh();

    $expense = Expense::factory()->create([
        'airline_id' => null,
        'type'       => ExpenseType::DAILY,
    ]);

    /** @var RecurringFinanceService $recurringFService */
    $recurringFService = app(RecurringFinanceService::class);
    $recurringFService->processExpenses();

    $this->assertDatabaseHas('journal_transactions', [
        'journal_id'     => $airline->journal->id,
        'ref_model_type' => Expense::class,
        'ref_model_id'   => $expense->id,
        'debit'          => Money::createFromAmount($expense->amount)->toAmount(),
        'post_date'      => Carbon::getTestNow(),
    ]);

    $this->assertDatabaseHas('journal_transactions', [
        'journal_id'     => $airline2->journal->id,
        'ref_model_type' => Expense::class,
        'ref_model_id'   => $expense->id,
        'debit'          => Money::createFromAmount($expense->amount)->toAmount(),
        'post_date'      => Carbon::getTestNow(),
    ]);
});

test('get all airline transactions between loads journals in one query', function () {
    Airline::factory()->create(['icao' => 'BBBBB', 'iata' => 'BB']);
    Airline::factory()->create(['icao' => 'AAAAA', 'iata' => 'AA']);

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(FinanceService::class)->getAllAirlineTransactionsBetween(now()->format('Y-m'));

    $journalQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => preg_match('/\bfrom\s+[`"]?journals[`"]?\b/i', $query) === 1)
        ->values();

    expect($journalQueries)->toHaveCount(1);
});
