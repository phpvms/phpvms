<?php

namespace Tests;

use App\Models\Acars;
use App\Models\Bid;
use App\Models\Enums\AcarsType;
use App\Models\Enums\PirepState;
use App\Models\File;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\SimBrief;
use App\Models\User;
use App\Services\SimBriefService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * A pilot must not act on another pilot's PIREPs, bids or SimBrief briefings.
 */
final class OwnershipTest extends TestCase
{
    private User $owner;

    private User $attacker;

    private Pirep $pirep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');

        $this->owner = User::factory()->create();
        $this->attacker = User::factory()->create();
        $this->pirep = Pirep::factory()->create([
            'user_id' => $this->owner->id,
            'state'   => PirepState::IN_PROGRESS,
        ]);
    }

    public function test_cannot_cancel_another_pilots_pirep(): void
    {
        $this->put('/api/pireps/'.$this->pirep->id.'/cancel', [], [], $this->attacker)->assertForbidden();
        $this->delete('/api/pireps/'.$this->pirep->id.'/cancel', [], [], $this->attacker)->assertForbidden();

        $this->assertEquals(PirepState::IN_PROGRESS, $this->pirep->fresh()->state);
    }

    public function test_cannot_write_to_another_pilots_pirep(): void
    {
        $id = $this->pirep->id;

        $this->post('/api/pireps/'.$id.'/comments', ['comment' => 'x'], [], $this->attacker)->assertForbidden();
        $this->post('/api/pireps/'.$id.'/fields', ['fields' => ['a' => 'b']], [], $this->attacker)->assertForbidden();
        $this->post('/api/pireps/'.$id.'/finances/recalculate', [], [], $this->attacker)->assertForbidden();
        $this->delete('/api/pireps/'.$id.'/route', [], [], $this->attacker)->assertForbidden();
    }

    public function test_owner_can_still_cancel_and_delete_their_route(): void
    {
        Acars::factory()->create(['pirep_id' => $this->pirep->id, 'type' => AcarsType::ROUTE]);

        $this->delete('/api/pireps/'.$this->pirep->id.'/route', [], [], $this->owner)->assertOk();
        $this->put('/api/pireps/'.$this->pirep->id.'/cancel', [], [], $this->owner)->assertOk();

        $this->assertEquals(PirepState::CANCELLED, $this->pirep->fresh()->state);
        $this->assertEquals(0, Acars::where('pirep_id', $this->pirep->id)->count());
    }

    public function test_update_ignores_state_and_id(): void
    {
        $this->post('/api/pireps/'.$this->pirep->id.'/update', [
            'state' => PirepState::ACCEPTED,
            'id'    => 'hijacked-id',
            'notes' => 'cruise',
        ], [], $this->owner)->assertOk();

        $pirep = $this->pirep->fresh();
        $this->assertNotNull($pirep);
        $this->assertEquals(PirepState::IN_PROGRESS, $pirep->state);
        $this->assertEquals('cruise', $pirep->notes);
    }

    public function test_filing_keeps_the_owner_and_ignores_another_pilots_briefing(): void
    {
        $briefing = SimBrief::factory()->create(['user_id' => $this->attacker->id]);

        $this->post('/api/pireps/'.$this->pirep->id.'/file', [
            'distance'    => 100,
            'flight_time' => 60,
            'user_id'     => $this->attacker->id,
            'simbrief_id' => $briefing->id,
        ], [], $this->owner)->assertOk();

        $this->assertEquals($this->owner->id, $this->pirep->fresh()->user_id);
        $this->assertNull($briefing->fresh()->pirep_id);
    }

    public function test_cannot_change_bids_for_another_user(): void
    {
        $flight = Flight::factory()->create(['airline_id' => $this->owner->airline_id]);

        $this->put('/api/user/bids', ['id' => $this->owner->id, 'flight_id' => $flight->id], [], $this->attacker)
            ->assertForbidden();
        $this->assertEquals(0, Bid::where('user_id', $this->owner->id)->count());

        Bid::create(['user_id' => $this->owner->id, 'flight_id' => $flight->id]);
        $this->delete('/api/user/bids', ['id' => $this->owner->id, 'flight_id' => $flight->id], [], $this->attacker)
            ->assertForbidden();
        $this->assertEquals(1, Bid::where('user_id', $this->owner->id)->count());
    }

    public function test_simbrief_briefings_are_scoped_to_their_owner(): void
    {
        $briefing = SimBrief::factory()->create([
            'user_id'   => $this->owner->id,
            'acars_xml' => '<secret/>',
        ]);

        $this->get('/api/flights/'.$briefing->id.'/briefing', [], $this->attacker)->assertNotFound();

        $this->actingAs($this->attacker);
        $this->get('/simbrief/'.$briefing->id.'/cancel');
        $this->assertNotNull(SimBrief::find($briefing->id));

        $this->get('/simbrief/'.$briefing->id.'/generate_new');
        $this->assertNotNull(SimBrief::find($briefing->id));

        $this->get('/simbrief/'.$briefing->id)->assertRedirect(route('frontend.flights.index'));
        // The test harness rethrows; in a request this renders as a 404.
        $this->expectException(ModelNotFoundException::class);
        $this->get('/pireps/create?sb_id='.$briefing->id);
    }

    public function test_downloads_ignore_unknown_file_owners(): void
    {
        File::create([
            'name'         => 'x',
            'path'         => 'x',
            'ref_model'    => 'Illuminate\Support\Collection',
            'ref_model_id' => '1',
        ]);

        $this->actingAs($this->attacker)->get('/downloads')->assertOk();
    }

    public function test_api_key_regenerates_only_on_post(): void
    {
        $old = $this->owner->api_key;
        $this->actingAs($this->owner);

        // A GET (a forged link or image) must not reach the regeneration action.
        $route = app('router')->getRoutes()->match(Request::create('/profile/regen_apikey', 'GET'));
        $this->assertNotEquals('regen_apikey', $route->getActionMethod());

        $this->post(route('frontend.profile.regen_apikey'))->assertRedirect();
        $this->assertNotEquals($old, $this->owner->fresh()->api_key);
    }

    public function test_owner_cancelling_their_briefing_deletes_it(): void
    {
        $briefing = SimBrief::factory()->create(['user_id' => $this->owner->id]);

        $this->actingAs($this->owner)->get('/simbrief/'.$briefing->id.'/cancel');

        $this->assertNull(SimBrief::find($briefing->id));
    }

    public function test_web_form_ignores_state_and_user_id(): void
    {
        $pirep = Pirep::factory()->create(['user_id' => $this->owner->id, 'state' => PirepState::DRAFT]);

        $this->actingAs($this->owner)->put(route('frontend.pireps.update', $pirep->id), [
            'airline_id'     => $pirep->airline_id,
            'flight_number'  => 100,
            'dpt_airport_id' => $pirep->dpt_airport_id,
            'arr_airport_id' => $pirep->arr_airport_id,
            'hours'          => 1,
            'minutes'        => 0,
            'block_fuel'     => 100,
            'fuel_used'      => 50,
            'submit'         => 'save',
            'state'          => PirepState::ACCEPTED,
            'user_id'        => $this->attacker->id,
        ])->assertRedirect();

        $pirep = $pirep->fresh();
        $this->assertNotEquals(PirepState::ACCEPTED, $pirep->state);
        $this->assertEquals($this->owner->id, $pirep->user_id);
    }

    public function test_cannot_take_over_another_pilots_briefing_by_ofp_id(): void
    {
        $briefing = SimBrief::factory()->create(['user_id' => $this->owner->id]);
        $this->mockXmlResponse(['simbrief/briefing.xml', 'simbrief/acars_briefing.xml']);

        $result = app(SimBriefService::class)->downloadOfp(
            (string) $this->attacker->id,
            (string) $briefing->id,
            Flight::factory()->create()->id,
            'ac',
        );

        $this->assertNull($result);
        $this->assertEquals($this->owner->id, $briefing->fresh()->user_id);
    }

    public function test_acars_ids_cannot_overwrite_another_pireps_rows(): void
    {
        $victim = Pirep::factory()->create(['user_id' => $this->attacker->id, 'state' => PirepState::IN_PROGRESS]);
        $cases = [
            'acars/position' => ['positions', AcarsType::FLIGHT_PATH, ['lat' => 1, 'lon' => 1]],
            'acars/logs'     => ['logs', AcarsType::LOG, ['log' => 'x']],
            'acars/events'   => ['events', AcarsType::LOG, ['event' => 'x']],
            'route'          => ['route', AcarsType::ROUTE, ['name' => 'WPT', 'order' => 1, 'lat' => 1, 'lon' => 1]],
        ];

        foreach ($cases as $uri => [$key, $type, $fields]) {
            $row = Acars::factory()->create(['pirep_id' => $victim->id, 'type' => $type]);

            $this->post('/api/pireps/'.$this->pirep->id.'/'.$uri, [$key => [array_merge(['id' => $row->id], $fields)]], [], $this->owner)
                ->assertSuccessful();

            $this->assertEquals($victim->id, $row->fresh()->pirep_id, $uri);
        }
    }

    public function test_admin_api_key_regeneration_is_not_a_get(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/admin/users/1/regen_apikey', 'POST'));
        $this->assertEquals('regen_apikey', $route->getActionMethod());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class);
        app('router')->getRoutes()->match(Request::create('/admin/users/1/regen_apikey', 'GET'));
    }
}
