<?php

namespace Tests;

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
use App\Models\Subfleet;
use App\Models\User;
use App\Repositories\ExpenseRepository;
use App\Repositories\JournalRepository;
use App\Services\BidService;
use App\Services\FareService;
use App\Services\Finance\PirepFinanceService;
use App\Services\Finance\RecurringFinanceService;
use App\Services\FinanceService;
use App\Services\FleetService;
use App\Services\PirepService;
use App\Support\Math;
use App\Support\Money;
use Exception;

final class FinanceTest extends TestCase
{
    private ExpenseRepository $expenseRepo;

    private FareService $fareSvc;

    private PirepFinanceService $financeSvc;

    private FleetService $fleetSvc;

    private PirepService $pirepSvc;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');
        $this->addData('fleet');

        $this->expenseRepo = app(ExpenseRepository::class);
        $this->fareSvc = app(FareService::class);
        $this->financeSvc = app(PirepFinanceService::class);
        $this->fleetSvc = app(FleetService::class);
        $this->pirepSvc = app(PirepService::class);
    }

    /**
     * Create a user and a PIREP, that has all of the data filled out
     * so that we can test all of the disparate parts of the finances
     *
     * @throws Exception
     */
    public function createFullPirep(?Airline $airline = null): array
    {
        /**
         * Setup tests
         */
        $subfleet = $this->createSubfleetWithAircraft(2);
        $subfleet['subfleet']->cost_block_hour = 10;
        $subfleet['subfleet']->save();

        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);
        $rank->acars_base_pay_rate = 10;
        $rank->save();

        $this->fleetSvc->addSubfleetToRank($subfleet['subfleet'], $rank);

        /** @var Airport $dpt_apt */
        $dpt_apt = Airport::factory()->create([
            'ground_handling_cost' => 10,
            'fuel_jeta_cost'       => 10,
        ]);

        /** @var Airport $arr_apt */
        $arr_apt = Airport::factory()->create([
            'ground_handling_cost' => 10,
            'fuel_jeta_cost'       => 10,
        ]);

        /** @var User $user */
        $u = [
            'rank_id' => $rank->id,
        ];

        if (!empty($airline)) {
            $u['airline_id'] = $airline->id;
        }

        $user = User::factory()->create($u);
        $user->airline->initJournal('USD');

        /** @var Flight $flight */
        $flight = Flight::factory()->create([
            'airline_id'     => $user->airline_id,
            'dpt_airport_id' => $dpt_apt->icao,
            'arr_airport_id' => $arr_apt->icao,
        ]);

        /** @var Pirep $pirep */
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

        /** @var Fare $fares */
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
            $this->fareSvc->setForSubfleet($subfleet['subfleet'], $fare);
        }

        // Add an expense
        Expense::factory()->create([
            'airline_id' => null,
            'amount'     => 100,
        ]);

        // Add a subfleet expense
        Expense::factory()->create([
            'ref_model'    => Subfleet::class,
            'ref_model_id' => $subfleet['subfleet']->id,
            'amount'       => 200,
        ]);

        // Add expenses for airports
        Expense::factory()->create([
            'ref_model'    => Airport::class,
            'ref_model_id' => $dpt_apt->id,
            'amount'       => 50,
        ]);

        Expense::factory()->create([
            'ref_model'    => Airport::class,
            'ref_model_id' => $arr_apt->id,
            'amount'       => 100,
        ]);

        $pirep = $this->pirepSvc->create($pirep, []);

        return [$user, $pirep, $fares];
    }

    /**
     * Make sure that the API is returning the fares properly for a subfleet on a flight
     * https://github.com/nabeelio/phpvms/issues/899
     *
     * The fares, etc for a subfleet has to be adjusted to the fleet
     * https://github.com/nabeelio/phpvms/issues/905
     */
    public function test_flight_fares_over_api(): void
    {
        $this->updateSetting('pireps.only_aircraft_at_dpt_airport', false);
        $this->updateSetting('pireps.restrict_aircraft_to_rank', false);

        $this->user = User::factory()->create();

        /** @var Flight $flight */
        $flight = Flight::factory()->create();

        /** @var Subfleet $subfleet */
        $subfleet = Subfleet::factory()->create();
        $this->fleetSvc->addSubfleetToFlight($subfleet, $flight);

        /**
         * Set a base fare
         * Then override on multiple layers - subfleet modifies the cost, the flight modifies
         * the price. This should then all be reflected as we go down the chain. This is
         * mostly for the output side
         */
        /** @var Fare $fare */
        $fare = Fare::factory()->create([
            'price'    => 10,
            'cost'     => 20,
            'capacity' => 100,
        ]);

        $this->fareSvc->setForSubfleet($subfleet, $fare, [
            'capacity' => 200,
        ]);

        $this->fareSvc->setForFlight($flight, $fare, [
            'price' => 50,
        ]);

        $flight = $this->fareSvc->getReconciledFaresForFlight($flight);

        $this->assertEquals(50, $flight->subfleets[0]->fares[0]->price);
        $this->assertEquals(200, $flight->subfleets[0]->fares[0]->capacity);
        $this->assertEquals(20, $flight->subfleets[0]->fares[0]->cost);

        //
        // set an override now (but on the flight)
        //

        $req = $this->get('/api/flights/'.$flight->id);
        $req->assertStatus(200);

        $body = $req->json()['data'];
        $this->assertEquals($flight->id, $body['id']);

        // Fares, etc, should be adjusted, per-subfleet
        $this->assertCount(1, $body['subfleets']);
        $this->assertEquals(50, $body['subfleets'][0]['fares'][0]['price']);
        $this->assertEquals(200, $body['subfleets'][0]['fares'][0]['capacity']);
        $this->assertEquals(20, $body['subfleets'][0]['fares'][0]['cost']);

        $req = $this->get('/api/flights/search?flight_id='.$flight->id);
        $req->assertStatus(200);

        $body = $req->json()['data'][0];
        $this->assertEquals($flight->id, $body['id']);

        // Fares, etc, should be adjusted, per-subfleet
        $this->assertCount(1, $body['subfleets']);
        $this->assertEquals(50, $body['subfleets'][0]['fares'][0]['price']);
        $this->assertEquals(200, $body['subfleets'][0]['fares'][0]['capacity']);
        $this->assertEquals(20, $body['subfleets'][0]['fares'][0]['cost']);
    }

    public function test_flight_fares_over_api_on_user_bids(): void
    {
        $this->updateSetting('pireps.only_aircraft_at_dpt_airport', false);
        $this->updateSetting('pireps.restrict_aircraft_to_rank', false);

        /** @var BidService $bidSvc */
        $bidSvc = app(BidService::class);

        $this->user = User::factory()->create();

        /** @var Flight $flight */
        $flight = Flight::factory()->create();

        /** @var Subfleet $subfleet */
        $subfleet = Subfleet::factory()->create();
        $this->fleetSvc->addSubfleetToFlight($subfleet, $flight);

        /** @var Fare $fare */
        $fare = Fare::factory()->create();

        $this->fareSvc->setForFlight($flight, $fare);

        //
        // set an override now (but on the flight)
        //
        $this->fareSvc->setForFlight($flight, $fare, ['price' => 50]);
        $bid = $bidSvc->addBid($flight, $this->user);

        $req = $this->get('/api/user/bids');
        $req->assertStatus(200);

        $body = $req->json()['data'];
        $this->assertEquals($flight->id, $body[0]['flight_id']);
        $this->assertCount(1, $body[0]['flight']['subfleets']);
        $this->assertEquals(50, $body[0]['flight']['fares'][0]['price']);
        $this->assertEquals($fare->capacity, $body[0]['flight']['fares'][0]['capacity']);
    }

    public function test_subfleet_fares_over_api(): void
    {
        $this->updateSetting('pireps.only_aircraft_at_dpt_airport', false);
        $this->updateSetting('pireps.restrict_aircraft_to_rank', false);

        /**
         * Add a user and flights
         */
        $this->user = User::factory()->create();
        $flight = $this->addFlight($this->user);

        /** @var FareService $fare_svc */
        $fare_svc = app(FareService::class);

        /** @var \App\Models\Fare $fare */
        $fare = Fare::factory()->create();
        $fare_svc->setForSubfleet($flight->subfleets[0], $fare, ['price' => 50]);

        // Get from API
        $req = $this->get('/api/flights/'.$flight->id);
        $req->assertStatus(200);

        $body = $req->json()['data'];
        $this->assertEquals($flight->id, $body['id']);
        $this->assertCount(1, $body['subfleets']);
        $this->assertEquals(50, $body['subfleets'][0]['fares'][0]['price']);
        $this->assertEquals($fare->capacity, $body['subfleets'][0]['fares'][0]['capacity']);
    }

    /**
     * Assign percentage values and make sure they're valid
     */
    public function test_flight_fare_override_as_percent(): void
    {
        /** @var Flight $flight */
        $flight = Flight::factory()->create();

        /** @var \App\Models\Fare $fare */
        $fare = Fare::factory()->create();

        // Subfleet needs to be attached to a flight
        $subfleet = Subfleet::factory()->create();
        $this->fleetSvc->addSubfleetToFlight($subfleet, $flight);

        $percent_incr = '120%';
        $percent_decr = '80%';
        $percent_200 = '200%';

        $new_price = Math::getPercent($fare->price, $percent_incr);
        $new_cost = Math::getPercent($fare->cost, $percent_decr);
        $new_capacity = Math::getPercent($fare->capacity, $percent_200);

        $this->fareSvc->setForFlight($flight, $fare, [
            'price'    => $percent_incr,
            'cost'     => $percent_decr,
            'capacity' => $percent_200,
        ]);

        // A subfleet is required to be passed in
        $ac_fares = $this->fareSvc->getAllFares($flight, $subfleet);

        $this->assertCount(1, $ac_fares);
        $this->assertEquals($new_price, $ac_fares[0]->price);
        $this->assertEquals($new_cost, $ac_fares[0]->cost);
        $this->assertEquals($new_capacity, $ac_fares[0]->capacity);
    }

    public function test_subfleet_fares_no_override(): void
    {
        $subfleet = Subfleet::factory()->create();
        $fare = Fare::factory()->create();

        $this->fareSvc->setForSubfleet($subfleet, $fare);
        $subfleet_fares = $this->fareSvc->getForSubfleet($subfleet);

        $this->assertCount(1, $subfleet_fares);
        $this->assertEquals($fare->price, $subfleet_fares->get(0)->price);
        $this->assertEquals($fare->capacity, $subfleet_fares->get(0)->capacity);

        //
        // set an override now
        //
        $this->fareSvc->setForSubfleet($subfleet, $fare, [
            'price'    => 50,
            'capacity' => 400,
        ]);

        // look for them again
        $subfleet_fares = $this->fareSvc->getForSubfleet($subfleet);

        $this->assertCount(1, $subfleet_fares);
        $this->assertEquals(50, $subfleet_fares[0]->price);
        $this->assertEquals(400, $subfleet_fares[0]->capacity);

        // delete
        $this->fareSvc->delFareFromSubfleet($subfleet, $fare);
        $this->assertCount(0, $this->fareSvc->getForSubfleet($subfleet));
    }

    public function test_subfleet_fares_override(): void
    {
        $subfleet = Subfleet::factory()->create();
        $fare = Fare::factory()->create();

        $this->fareSvc->setForSubfleet($subfleet, $fare, [
            'price'    => 50,
            'capacity' => 400,
        ]);

        $ac_fares = $this->fareSvc->getForSubfleet($subfleet);

        $this->assertCount(1, $ac_fares);
        $this->assertEquals(50, $ac_fares[0]->price);
        $this->assertEquals(400, $ac_fares[0]->capacity);

        //
        // update the override to a different amount and make sure it updates
        //

        $this->fareSvc->setForSubfleet($subfleet, $fare, [
            'price'    => 150,
            'capacity' => 50,
        ]);

        $ac_fares = $this->fareSvc->getForSubfleet($subfleet);

        $this->assertCount(1, $ac_fares);
        $this->assertEquals(150, $ac_fares[0]->price);
        $this->assertEquals(50, $ac_fares[0]->capacity);

        // delete
        $this->fareSvc->delFareFromSubfleet($subfleet, $fare);
        $this->assertCount(0, $this->fareSvc->getForSubfleet($subfleet));
    }

    /**
     * Assign percentage values and make sure they're valid
     */
    public function test_subfleet_fare_override_as_percent(): void
    {
        $subfleet = Subfleet::factory()->create();
        $fare = Fare::factory()->create();

        $percent_incr = '20%';
        $percent_decr = '-20%';
        $percent_200 = '200%';

        $new_price = Math::getPercent($fare->price, $percent_incr);
        $new_cost = Math::getPercent($fare->cost, $percent_decr);
        $new_capacity = Math::getPercent($fare->capacity, $percent_200);

        $this->fareSvc->setForSubfleet($subfleet, $fare, [
            'price'    => $percent_incr,
            'cost'     => $percent_decr,
            'capacity' => $percent_200,
        ]);

        $ac_fares = $this->fareSvc->getForSubfleet($subfleet);

        $this->assertCount(1, $ac_fares);
        $this->assertEquals($new_price, $ac_fares[0]->price);
        $this->assertEquals($new_cost, $ac_fares[0]->cost);
        $this->assertEquals($new_capacity, $ac_fares[0]->capacity);
    }

    /**
     * Test getting the fares from the flight svc. Have a few base fares
     * and then override some of them
     */
    public function test_get_fares_with_overrides(): void
    {
        $flight = Flight::factory()->create();
        $subfleet = Subfleet::factory()->create();
        [$fare1, $fare2, $fare3, $fare4] = Fare::factory()->count(4)->create();

        // add to the subfleet, and just override one of them
        $this->fareSvc->setForSubfleet($subfleet, $fare1);
        $this->fareSvc->setForSubfleet($subfleet, $fare2, [
            'price'    => 100,
            'cost'     => 50,
            'capacity' => 25,
        ]);

        $this->fareSvc->setForSubfleet($subfleet, $fare3);

        // Now set the last one to the flight and then override stuff
        $this->fareSvc->setForFlight($flight, $fare3, [
            'price' => '300%',
            'cost'  => 250,
        ]);

        $fare3_price = Math::getPercent($fare3->price, 300);

        // Assign another one to the flight, that's not on the subfleet
        // This one should NOT be returned in the list of fares
        $this->fareSvc->setForFlight($flight, $fare4);

        $fares = $this->fareSvc->getAllFares($flight, $subfleet);
        $this->assertCount(3, $fares);

        foreach ($fares as $fare) {
            switch ($fare->id) {
                case $fare1->id:
                    $this->assertEquals($fare->price, $fare1->price);
                    $this->assertEquals($fare->cost, $fare1->cost);
                    $this->assertEquals($fare->capacity, $fare1->capacity);
                    break;

                case $fare2->id:
                    $this->assertEquals($fare->price, 100);
                    $this->assertEquals($fare->cost, 50);
                    $this->assertEquals($fare->capacity, 25);
                    break;

                case $fare3->id:
                    $this->assertEquals($fare->price, $fare3_price);
                    $this->assertEquals($fare->cost, 250);
                    $this->assertEquals($fare->capacity, $fare3->capacity);
                    break;
            }
        }
    }

    public function test_get_fares_no_flight_overrides(): void
    {
        $subfleet = Subfleet::factory()->create();
        [$fare1, $fare2, $fare3] = Fare::factory()->count(3)->create();

        // add to the subfleet, and just override one of them
        $this->fareSvc->setForSubfleet($subfleet, $fare1);
        $this->fareSvc->setForSubfleet($subfleet, $fare2, [
            'price'    => 100,
            'cost'     => 50,
            'capacity' => 25,
        ]);

        $this->fareSvc->setForSubfleet($subfleet, $fare3);

        $fares = $this->fareSvc->getAllFares(null, $subfleet);
        $this->assertCount(3, $fares);

        foreach ($fares as $fare) {
            switch ($fare->id) {
                case $fare1->id:
                    $this->assertEquals($fare->price, $fare1->price);
                    $this->assertEquals($fare->cost, $fare1->cost);
                    $this->assertEquals($fare->capacity, $fare1->capacity);
                    break;

                case $fare2->id:
                    $this->assertEquals($fare->price, 100);
                    $this->assertEquals($fare->cost, 50);
                    $this->assertEquals($fare->capacity, 25);
                    break;

                case $fare3->id:
                    $this->assertEquals($fare->price, $fare3->price);
                    $this->assertEquals($fare->cost, $fare3->cost);
                    $this->assertEquals($fare->capacity, $fare3->capacity);
                    break;
            }
        }
    }

    /**
     * Get the pilot pay, derived from the rank
     */
    public function test_get_pilot_pay_no_override(): void
    {
        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);
        $this->fleetSvc->addSubfleetToRank($subfleet['subfleet'], $rank);

        $this->user = User::factory()->create([
            'rank_id' => $rank->id,
        ]);

        $pirep = Pirep::factory()->create([
            'user_id'     => $this->user->id,
            'aircraft_id' => $subfleet['aircraft']->random(),
            'source'      => PirepSource::ACARS,
        ]);

        $rate = $this->financeSvc->getPilotPayRateForPirep($pirep);
        $this->assertEquals($rank->acars_base_pay_rate, $rate);
    }

    /**
     * Get the pilot pay, but include different overrides
     */
    public function test_get_pilot_pay_with_override(): void
    {
        $acars_pay_rate = 100;

        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);
        $this->fleetSvc->addSubfleetToRank($subfleet['subfleet'], $rank, [
            'acars_pay' => $acars_pay_rate,
        ]);

        $this->user = User::factory()->create([
            'rank_id' => $rank->id,
        ]);

        $pirep_acars = Pirep::factory()->create([
            'user_id'     => $this->user->id,
            'aircraft_id' => $subfleet['aircraft']->random(),
            'source'      => PirepSource::ACARS,
        ]);

        $rate = $this->financeSvc->getPilotPayRateForPirep($pirep_acars);
        $this->assertEquals($acars_pay_rate, $rate);

        // Change to a percentage
        $manual_pay_rate = '50%';
        $manual_pay_adjusted = Math::getPercent(
            $rank->manual_base_pay_rate,
            $manual_pay_rate
        );

        $this->fleetSvc->addSubfleetToRank($subfleet['subfleet'], $rank, [
            'manual_pay' => $manual_pay_rate,
        ]);

        $pirep_manual = Pirep::factory()->create([
            'user_id'     => $this->user->id,
            'aircraft_id' => $subfleet['aircraft']->random(),
            'source'      => PirepSource::MANUAL,
        ]);

        $rate = $this->financeSvc->getPilotPayRateForPirep($pirep_manual);
        $this->assertEquals($manual_pay_adjusted, $rate);

        // And make sure the original acars override still works
        $rate = $this->financeSvc->getPilotPayRateForPirep($pirep_acars);
        $this->assertEquals($acars_pay_rate, $rate);
    }

    /**
     * Get the payment for a pilot
     */
    public function test_get_pirep_pilot_pay(): void
    {
        $acars_pay_rate = 100;

        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);
        $this->fleetSvc->addSubfleetToRank($subfleet['subfleet'], $rank, [
            'acars_pay' => $acars_pay_rate,
        ]);

        $this->user = User::factory()->create([
            'rank_id' => $rank->id,
        ]);

        $pirep_acars = Pirep::factory()->create([
            'user_id'     => $this->user->id,
            'aircraft_id' => $subfleet['aircraft']->random(),
            'source'      => PirepSource::ACARS,
            'flight_time' => 60,
        ]);

        $payment = $this->financeSvc->getPilotPay($pirep_acars);
        $this->assertEquals(100, $payment->getValue());

        $pirep_acars = Pirep::factory()->create([
            'user_id'     => $this->user->id,
            'aircraft_id' => $subfleet['aircraft']->random(),
            'source'      => PirepSource::ACARS,
            'flight_time' => 90,
        ]);

        $payment = $this->financeSvc->getPilotPay($pirep_acars);
        $this->assertEquals($payment->getValue(), 150);
    }

    /**
     * @throws Exception
     */
    public function test_get_pirep_pilot_pay_with_fixed_price(): void
    {
        $acars_pay_rate = 100;

        $subfleet = $this->createSubfleetWithAircraft(2);
        $rank = $this->createRank(10, [$subfleet['subfleet']->id]);
        $this->fleetSvc->addSubfleetToRank($subfleet['subfleet'], $rank, [
            'acars_pay' => $acars_pay_rate,
        ]);

        $this->user = User::factory()->create([
            'rank_id' => $rank->id,
        ]);

        $flight = Flight::factory()->create([
            'airline_id' => $this->user->airline_id,
            'pilot_pay'  => 1000,
        ]);

        $pirep_acars = Pirep::factory()->create([
            'user_id'     => $this->user->id,
            'aircraft_id' => $subfleet['aircraft']->random(),
            'source'      => PirepSource::ACARS,
            'flight_id'   => $flight->id,
            'flight_time' => 60,
        ]);

        $payment = $this->financeSvc->getPilotPay($pirep_acars);
        $this->assertEquals(1000, $payment->getValue());

        $pirep_acars = Pirep::factory()->create([
            'user_id'     => $this->user->id,
            'aircraft_id' => $subfleet['aircraft']->random(),
            'source'      => PirepSource::ACARS,
            'flight_time' => 90,
        ]);

        $payment = $this->financeSvc->getPilotPay($pirep_acars);
        $this->assertEquals($payment->getValue(), 150);
    }

    /**
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function test_journal_operations(): void
    {
        $journalRepo = app(JournalRepository::class);

        $user = User::factory()->create();
        $journal = Journal::factory()->create();

        $journalRepo->post(
            $journal,
            Money::createFromAmount(100.5),
            null,
            $user
        );

        $balance = $journalRepo->getBalance($journal);
        $this->assertEquals(100.5, $balance->getValue());
        $this->assertEquals(100.5, $journal->balance->getValue());

        // add another transaction

        $journalRepo->post(
            $journal,
            Money::createFromAmount(24.5),
            null,
            $user
        );

        $balance = $journalRepo->getBalance($journal);
        $this->assertEquals(125, $balance->getValue());
        $this->assertEquals(125, $journal->balance->getValue());

        // debit an amount
        $journalRepo->post(
            $journal,
            null,
            Money::createFromAmount(25),
            $user
        );

        $balance = $journalRepo->getBalance($journal);
        $this->assertEquals(100, $balance->getValue());
        $this->assertEquals(100, $journal->balance->getValue());

        // find all transactions
        $transactions = $journalRepo->getAllForObject($user);

        $this->assertCount(3, $transactions['transactions']);
        $this->assertEquals(125, $transactions['credits']->getValue());
        $this->assertEquals(25, $transactions['debits']->getValue());
    }

    /**
     * @throws Exception
     */
    public function test_pirep_fares(): void
    {
        [$user, $pirep, $fares] = $this->createFullPirep();

        // Override the fares
        $fare_counts = [];
        foreach ($fares as $fare) {
            $fare_counts[] = new PirepFare([
                'fare_id' => $fare->id,
                'count'   => round($fare->capacity / 2),
            ]);
        }

        $this->fareSvc->saveToPirep($pirep, $fare_counts);
        $all_fares = $this->financeSvc->getFaresForPirep($pirep);

        $this->assertCount(3, $all_fares);
        $fare_counts = collect($fare_counts);
        foreach ($all_fares as $fare) {
            // $set_fare = $fare_counts->where('fare_id', $fare->id)->first();
            $this->assertEquals($fare['count'], $fare->count);
            $this->assertNotEmpty($fare['price']);
            $this->assertNotEmpty($fare['cost']);
            $this->assertNotEmpty($fare['capacity']);
        }
    }

    /**
     * Test that all expenses are pulled properly
     */
    public function test_pirep_expenses(): void
    {
        /** @var Airline $airline */
        $airline = Airline::factory()->create();

        /** @var Airline $airline2 */
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

        $expenses = $this->expenseRepo->getAllForType(
            ExpenseType::FLIGHT,
            $airline->id,
            Expense::class
        );

        $this->assertCount(2, $expenses);

        $found = $expenses->where('airline_id', null);
        $this->assertCount(1, $found);

        $found = $expenses->where('airline_id', $airline->id);
        $this->assertCount(1, $found);

        $found = $expenses->where('airline_id', $airline2->id);
        $this->assertCount(0, $found);

        /*
         * Test the subfleet class
         */

        $subfleet = Subfleet::factory()->create();
        Expense::factory()->create([
            'airline_id'   => null,
            'ref_model'    => Subfleet::class,
            'ref_model_id' => $subfleet->id,
        ]);

        $expenses = $this->expenseRepo->getAllForType(
            ExpenseType::FLIGHT,
            $airline->id,
            $subfleet
        );

        $this->assertCount(1, $expenses);

        $expense = $expenses->random();
        $this->assertEquals(Subfleet::class, $expense->ref_model);
        $obj = $expense->getReferencedObject();
        $this->assertEquals($obj->id, $expense->ref_model_id);
    }

    public function test_airport_expenses(): void
    {
        /** @var Airport $apt1 */
        $apt1 = Airport::factory()->create();

        /** @var Airport $apt2 */
        $apt2 = Airport::factory()->create();

        /** @var Airport $apt3 */
        $apt3 = Airport::factory()->create();

        Expense::factory()->create([
            'airline_id'   => null,
            'ref_model'    => Airport::class,
            'ref_model_id' => $apt1->id,
        ]);

        Expense::factory()->create([
            'airline_id'   => null,
            'ref_model'    => Airport::class,
            'ref_model_id' => $apt2->id,
        ]);

        Expense::factory()->create([
            'airline_id'   => null,
            'ref_model'    => Airport::class,
            'ref_model_id' => $apt3->id,
        ]);

        $expenses = $this->expenseRepo->getAllForType(
            ExpenseType::FLIGHT,
            null,
            Airport::class
        );

        $this->assertCount(3, $expenses);
    }

    /**
     * @throws Exception
     */
    public function test_pirep_finances(): void
    {
        $journalRepo = app(JournalRepository::class);

        [$user, $pirep, $fares] = $this->createFullPirep();
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

        $this->fareSvc->saveToPirep($pirep, $fareCounts);

        // This should process all of the
        $pirep = $this->pirepSvc->accept($pirep);

        $transactions = $journalRepo->getAllForObject($pirep);

        /** @var Money $credits */
        $credits = $transactions['credits'];

        /** @var Money $credits */
        $debits = $transactions['debits'];

        // $this->assertCount(9, $transactions['transactions']);
        $this->assertEquals(3020, $credits->getValue());
        $this->assertEquals(2050.4, $debits->getValue());

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
            $this->assertEquals($count, $find->count());
        }
    }

    /**
     * @throws Exception
     */
    public function test_pirep_finances_specific_expense(): void
    {
        $journalRepo = app(JournalRepository::class);

        // Add an expense that's only for a cargo flight
        Expense::factory()->create([
            'airline_id'  => null,
            'amount'      => 100,
            'flight_type' => FlightType::SCHED_CARGO,
        ]);

        [$user, $pirep, $fares] = $this->createFullPirep();
        $user->airline->initJournal(setting('units.currency', 'USD'));

        // Override the fares
        $fare_counts = [];
        foreach ($fares as $fare) {
            $fare_counts[] = new PirepFare([
                'fare_id' => $fare->id,
                'count'   => 10,
            ]);
        }

        $this->fareSvc->saveToPirep($pirep, $fare_counts);

        // This should process all of the
        $pirep = $this->pirepSvc->accept($pirep);

        $transactions = $journalRepo->getAllForObject($pirep);

        //        $this->assertCount(9, $transactions['transactions']);
        $this->assertEquals(3020, $transactions['credits']->getValue());
        $this->assertEquals(2050.4, $transactions['debits']->getValue());

        // Retrieve data from the pirep fares to make sure all of the fields were saved
        $saved_fares = PirepFare::where('pirep_id', $pirep->id)->get();
        $this->assertCount(3, $saved_fares);

        /** @var PirepFare $f */
        foreach ($saved_fares as $f) {
            /** @var Fare $original_fare */
            $original_fare = $fares->where('code', $f->code)->first();

            $this->assertEquals($original_fare->price, $f->price);
            $this->assertEquals($original_fare->cost, $f->cost);
            $this->assertEquals($original_fare->capacity, $f->capacity);
            $this->assertEquals($original_fare->type, $f->type);
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
            $this->assertEquals($count, $find->count());
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

        $this->fareSvc->saveToPirep($pirep2, $fare_counts);
        $pirep2 = $this->pirepSvc->accept($pirep2);

        // Retrieve data from the pirep fares to make sure all of the fields were saved
        $saved_fares = PirepFare::where('pirep_id', $pirep2->id)->get();
        $this->assertCount(3, $saved_fares);

        $transactions = $journalRepo->getAllForObject($pirep2);
        $this->assertEquals(3020, $transactions['credits']->getValue());
        $this->assertEquals(2150.4, $transactions['debits']->getValue());

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
            $this->assertEquals($count, $find->count());
        }
    }

    /**
     * @throws Exception
     */
    public function test_pirep_finances_expenses_multi_airline(): void
    {
        /** @var Airline $airline */
        $airline = Airline::factory()->create();

        /** @var JournalRepository $journalRepo */
        $journalRepo = app(JournalRepository::class);

        // Add an expense that's only for a cargo flight
        Expense::factory()->create(
            [
                'airline_id'  => null,
                'amount'      => 100,
                'flight_type' => FlightType::SCHED_CARGO,
            ]
        );

        [$user, $pirep, $fares] = $this->createFullPirep();
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
        /** @var Subfleet $subfleet */
        $subfleet = Subfleet::factory()->create();
        Expense::factory()->create([
            'airline_id'   => null,
            'amount'       => 100,
            'ref_model'    => Subfleet::class,
            'ref_model_id' => $subfleet->id,
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

        $this->fareSvc->saveToPirep($pirep, $fare_counts);

        // This should process all of the
        $pirep = $this->pirepSvc->accept($pirep);

        $transactions = $journalRepo->getAllForObject($pirep);

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
            $this->assertEquals($count, $find->count(), $type);
        }

        //        $this->assertCount(9, $transactions['transactions']);
        $this->assertEquals(3020, $transactions['credits']->getValue());
        $this->assertEquals(2050.4, $transactions['debits']->getValue());
    }

    /**
     * Test that all expenses are pulled properly
     */
    public function test_pirep_expenses_nightly()
    {
        /** @var JournalRepository $journalRepo */
        $journalRepo = app(JournalRepository::class);

        /** @var Airline $airline */
        $airline = Airline::factory()->create();

        /** @var Airline $airline2 */
        $airline2 = Airline::factory()->create();

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

        /** @var FinanceService $financeSvc */
        $financeSvc = app(FinanceService::class);

        $exp = Expense::factory()->make([
            'airline_id' => null,
            'type'       => ExpenseType::DAILY,
        ]);

        $financeSvc->addExpense($exp->toArray(), $subfleet);

        [$user, $pirep, $fares] = $this->createFullPirep();
        $pirep = $this->pirepSvc->accept($pirep);

        // $transactions = $journalRepo->getAllForObject($pirep);
        $txn_airline1 = $journalRepo->getAllForObject($airline);
        $txn_airline2 = $journalRepo->getAllForObject($airline2);

        /** @var RecurringFinanceService $recurringFService */
        $recurringFService = app(RecurringFinanceService::class);
        $recurringFService->processExpenses(ExpenseType::DAILY);

        $txn_airline1 = $journalRepo->getAllForObject($airline);
        $txn_airline2 = $journalRepo->getAllForObject($airline2);
        // dd($txn_airline1);
    }
}
