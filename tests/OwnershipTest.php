<?php

namespace Tests;

use App\Models\Acars;
use App\Models\Bid;
use App\Models\Enums\AcarsType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\File;
use App\Models\Flight;
use App\Models\JournalTransaction;
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

    public function test_update_checks_the_submitted_aircraft_against_the_rank(): void
    {
        $this->updateSetting('pireps.restrict_aircraft_to_rank', true);

        $allowed = $this->createSubfleetWithAircraft(1);
        $forbidden = $this->createSubfleetWithAircraft(1);
        $rank = $this->createRank(0, [$allowed['subfleet']->id]);
        $this->owner->update(['rank_id' => $rank->id]);

        $ownAircraft = $allowed['aircraft']->first();
        $this->pirep->update(['aircraft_id' => $ownAircraft->id]);

        $this->post('/api/pireps/'.$this->pirep->id.'/update', [
            'aircraft_id' => $forbidden['aircraft']->first()->id,
        ], [], $this->owner)->assertStatus(400);

        $this->assertEquals($ownAircraft->id, $this->pirep->fresh()->aircraft_id);
    }

    public function test_filing_cannot_switch_the_source_to_get_auto_approved(): void
    {
        $this->owner->rank->update(['auto_approve_acars' => false, 'auto_approve_manual' => true]);
        $this->pirep->update(['source' => PirepSource::ACARS]);

        $this->post('/api/pireps/'.$this->pirep->id.'/file', [
            'distance'    => 100,
            'flight_time' => 60,
            'source'      => PirepSource::MANUAL,
        ], [], $this->owner)->assertOk();

        $pirep = $this->pirep->fresh();
        $this->assertEquals(PirepSource::ACARS, $pirep->source);
        $this->assertEquals(PirepState::PENDING, $pirep->state);
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

    public function test_manual_pirep_ignores_protected_attributes(): void
    {
        $this->updateSetting('pilots.only_flights_from_current', false);
        $this->updateSetting('pireps.restrict_aircraft_to_rank', false);
        $this->owner->rank->update(['auto_approve_acars' => true, 'auto_approve_manual' => false]);

        $this->actingAs($this->owner)->post(route('frontend.pireps.store'), [
            'airline_id'     => $this->pirep->airline_id,
            'aircraft_id'    => $this->pirep->aircraft_id,
            'flight_number'  => 100,
            'dpt_airport_id' => $this->pirep->dpt_airport_id,
            'arr_airport_id' => $this->pirep->arr_airport_id,
            'hours'          => 1,
            'minutes'        => 0,
            'block_fuel'     => 100,
            'fuel_used'      => 50,
            'submit'         => 'submit',
            'state'          => PirepState::ACCEPTED,
            'source'         => PirepSource::ACARS,
            'user_id'        => $this->attacker->id,
        ])->assertRedirect();

        $pirep = Pirep::where('flight_number', '100')->latest()->first();
        $this->assertNotNull($pirep);
        $this->assertEquals($this->owner->id, $pirep->user_id);
        $this->assertNotEquals(PirepSource::ACARS, $pirep->source);
        $this->assertNotEquals(PirepState::ACCEPTED, $pirep->state);
    }

    public function test_cannot_read_another_pilots_pirep_finances(): void
    {
        $this->get('/api/pireps/'.$this->pirep->id.'/finances', [], $this->attacker)->assertForbidden();
        $this->get('/api/pireps/'.$this->pirep->id.'/finances', [], $this->owner)->assertOk();
    }

    public function test_recalculating_finances_requires_an_accepted_pirep(): void
    {
        $this->post('/api/pireps/'.$this->pirep->id.'/finances/recalculate', [], [], $this->owner)
            ->assertStatus(400);

        $this->assertSame(0, JournalTransaction::where('ref_model_id', $this->pirep->id)->count());
    }

    public function test_owner_cannot_change_or_resubmit_an_accepted_pirep(): void
    {
        $this->pirep->update(['state' => PirepState::ACCEPTED, 'flight_time' => 60]);

        $this->actingAs($this->owner)->put(route('frontend.pireps.update', [$this->pirep->id]), [
            'airline_id'     => $this->pirep->airline_id,
            'flight_number'  => $this->pirep->flight_number,
            'dpt_airport_id' => $this->pirep->dpt_airport_id,
            'arr_airport_id' => $this->pirep->arr_airport_id,
            'hours'          => 10,
            'minutes'        => 0,
            'block_fuel'     => 100,
            'fuel_used'      => 50,
            'submit'         => 'delete',
        ])->assertRedirect();

        $pirep = $this->pirep->fresh();
        $this->assertNotNull($pirep);
        $this->assertEquals(60, $pirep->flight_time);

        $this->actingAs($this->owner)->post(route('frontend.pireps.submit', [$this->pirep->id]))->assertRedirect();
        $this->assertEquals(PirepState::ACCEPTED, $this->pirep->fresh()->state);
    }

    public function test_profile_update_ignores_server_owned_columns(): void
    {
        $apiKey = $this->owner->api_key;

        $this->actingAs($this->owner)->put('/profile/'.$this->owner->id, [
            'name'            => 'Renamed',
            'email'           => $this->owner->email,
            'airline_id'      => $this->owner->airline_id,
            'api_key'         => 'chosen-key',
            'pilot_id'        => $this->attacker->pilot_id,
            'curr_airport_id' => 'ZZZZ',
            'rank_id'         => 999,
        ])->assertRedirect();

        $user = $this->owner->fresh();
        $this->assertEquals('Renamed', $user->name);
        $this->assertEquals($apiKey, $user->api_key);
        $this->assertNotEquals($this->attacker->pilot_id, $user->pilot_id);
        $this->assertNotEquals('ZZZZ', $user->curr_airport_id);
        $this->assertNotEquals(999, $user->rank_id);
    }

    public function test_cannot_read_another_pilots_bids_fleet_or_pireps_by_id(): void
    {
        foreach (['bids', 'fleet', 'pireps'] as $endpoint) {
            $this->get('/api/user/'.$endpoint.'?id='.$this->owner->id, [], $this->attacker)->assertForbidden();
            $this->get('/api/users/'.$this->owner->id.'/'.$endpoint, [], $this->attacker)->assertForbidden();
        }

        // Own id and "me" still work, and an admin can read anyone
        $this->get('/api/users/'.$this->owner->id.'/bids', [], $this->owner)->assertOk();
        $this->get('/api/user/bids?id=me', [], $this->owner)->assertOk();
        $this->get('/api/users/'.$this->owner->id.'/bids', [], User::find(1))->assertOk();
    }

    public function test_public_pirep_endpoint_hides_comments_from_anonymous_callers(): void
    {
        $this->pirep->comments()->create(['user_id' => $this->owner->id, 'comment' => 'private note']);

        $anonymous = $this->get('/api/pireps/'.$this->pirep->id)->assertOk()->json('data');
        $this->assertArrayNotHasKey('comments', $anonymous);

        $owner = $this->get('/api/pireps/'.$this->pirep->id, [], $this->owner)->assertOk()->json('data');
        $this->assertCount(1, $owner['comments']);
    }

    public function test_submitting_a_saved_draft_runs_the_departure_check(): void
    {
        $this->updateSetting('pilots.only_flights_from_current', true);
        $this->updateSetting('pireps.restrict_aircraft_to_rank', false);
        $this->updateSetting('pireps.only_aircraft_at_dpt_airport', false);
        $this->owner->update(['curr_airport_id' => $this->pirep->arr_airport_id]);
        $this->pirep->update(['state' => PirepState::DRAFT]);

        $this->actingAs($this->owner)->post(route('frontend.pireps.submit', [$this->pirep->id]))->assertRedirect();
        $this->assertEquals(PirepState::DRAFT, $this->pirep->fresh()->state);

        $this->owner->update(['curr_airport_id' => $this->pirep->dpt_airport_id]);
        $this->actingAs($this->owner)->post(route('frontend.pireps.submit', [$this->pirep->id]))->assertRedirect();
        $this->assertEquals(PirepState::PENDING, $this->pirep->fresh()->state);
    }

    public function test_route_post_ignores_unknown_columns(): void
    {
        $this->post('/api/pireps/'.$this->pirep->id.'/route', [
            'route' => [
                ['id' => 'client-1', 'order' => 1, 'name' => 'WPT1', 'lat' => 1.1, 'lon' => 2.2, 'bogus' => 'x'],
                ['order' => 2, 'name' => 'WPT2', 'lat' => 1.2, 'lon' => 2.3, 'not_a_column' => 'y'],
            ],
        ], [], $this->owner)->assertOk();

        $this->assertEquals(2, Acars::where('pirep_id', $this->pirep->id)->where('type', AcarsType::ROUTE)->count());
    }

    public function test_pirep_comments_are_only_readable_by_the_owner_or_an_admin(): void
    {
        $this->pirep->comments()->create(['user_id' => $this->owner->id, 'comment' => 'private note']);
        $uri = '/api/pireps/'.$this->pirep->id.'/comments';

        $this->get($uri, [], $this->attacker)->assertForbidden();
        $this->assertCount(1, $this->get($uri, [], $this->owner)->assertOk()->json('data'));
        $this->assertCount(1, $this->get($uri, [], User::find(1))->assertOk()->json('data'));

        // The public pirep endpoint follows the same rule for an API-key caller
        $this->assertArrayNotHasKey('comments', $this->get('/api/pireps/'.$this->pirep->id, [], $this->attacker)->json('data'));
    }

    public function test_linked_provider_ids_are_hidden_from_other_pilots(): void
    {
        $this->owner->update(['discord_id' => '123', 'vatsim_id' => 456, 'ivao_id' => 789]);

        $other = $this->get('/api/users/'.$this->owner->id, [], $this->attacker)->assertOk()->json('data');
        $this->assertArrayNotHasKey('discord_id', $other);
        $this->assertArrayNotHasKey('vatsim_id', $other);
        $this->assertArrayNotHasKey('ivao_id', $other);

        $self = $this->get('/api/user', [], $this->owner)->assertOk()->json('data');
        $this->assertEquals('123', $self['discord_id']);

        $admin = $this->get('/api/users/'.$this->owner->id, [], User::find(1))->assertOk()->json('data');
        $this->assertEquals('123', $admin['discord_id']);
    }
}
