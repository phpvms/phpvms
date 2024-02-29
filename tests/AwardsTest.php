<?php

namespace Tests;

use App\Models\Award;
use App\Models\Pirep;
use App\Models\User;
use App\Models\UserAward;
use App\Services\AwardService;
use App\Services\PirepService;
use Modules\Awards\Awards\FlightRouteAwards;
use Modules\Awards\Awards\PilotFlightAwards;

final class AwardsTest extends TestCase
{
    /** @var AwardService */
    private AwardService $awardSvc;

    private PirepService $pirepSvc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');
        $this->addData('fleet');
        $this->awardSvc = app(AwardService::class);
        $this->pirepSvc = app(PirepService::class);
    }

    /**
     * Make sure the awards classes are returned
     */
    public function testGetAwardsClasses(): void
    {
        $classes = $this->awardSvc->findAllAwardClasses();
        $this->assertGreaterThanOrEqual(2, $classes);
    }

    /**
     * Test to make sure that the award is actually given out
     *
     * @throws \Exception
     */
    public function testAwardsGiven(): void
    {
        // Create one award that's given out with one flight
        $award = Award::factory()->create([
            'ref_model'        => PilotFlightAwards::class,
            'ref_model_params' => 1,
        ]);

        $user = User::factory()->create([
            'flights' => 0,
        ]);

        $pirep = Pirep::factory()->create([
            'airline_id' => $user->airline->id,
            'user_id'    => $user->id,
        ]);

        $this->pirepSvc->create($pirep);
        $this->pirepSvc->accept($pirep);

        $w = [
            'user_id'  => $user->id,
            'award_id' => $award->id,
        ];

        // Make sure only one is awarded
        $this->assertEquals(1, UserAward::where($w)->count(['id']));

        $found_award = UserAward::where($w)->first();
        $this->assertNotNull($found_award);
    }

    /**
     * Test the flight route
     */
    public function testFlightRouteAward(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'flights' => 0,
        ]);

        /** @var \App\Models\Award $award */
        $award = Award::factory()->create([
            'ref_model'        => FlightRouteAwards::class,
            'ref_model_params' => 1,
        ]);

        /** @var Pirep $pirep */
        $pirep = Pirep::factory()->create([
            'airline_id' => $user->airline->id,
            'user_id'    => $user->id,
        ]);

        $flightAward = new FlightRouteAwards($award, $user);

        // Test no last PIREP for the user
        $this->assertFalse($flightAward->check(''));

        // Reinit award, add a last user PIREP id
        $user->last_pirep_id = $pirep->id;
        $user->save();

        $flightAward = new FlightRouteAwards($award, $user);
        $validStrs = [
            $pirep->dpt_airport_id.':'.$pirep->arr_airport_id,
            $pirep->dpt_airport_id.':'.$pirep->arr_airport_id.' ',
            $pirep->dpt_airport_id.':'.$pirep->arr_airport_id.':',
            strtolower($pirep->dpt_airport_id).':'.strtolower($pirep->arr_airport_id),
        ];

        foreach ($validStrs as $str) {
            $this->assertTrue($flightAward->check($str));
        }

        // Check error conditions
        $errStrs = [
            '',
            ' ',
            ':',
            'ABCD:EDFSDF',
            $pirep->dpt_airport_id.':',
            ':'.$pirep->arr_airport_id,
            ':'.$pirep->arr_airport_id.':',
        ];

        foreach ($errStrs as $err) {
            $this->assertFalse($flightAward->check($err));
        }
    }
}
