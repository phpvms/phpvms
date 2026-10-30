<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Pages\ViewPirep;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Acars;
use App\Models\Bid;
use App\Models\File;
use App\Models\Flight;
use App\Models\Permission;
use App\Models\Pirep;
use App\Models\SimBrief;
use App\Models\User;
use App\Services\PirepService;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

/**
 * Cross-user access checks for the advisories in this branch: a pilot must not
 * act on another pilot's PIREPs, bids or SimBrief briefings.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->attacker = User::factory()->create();
    $this->pirep = Pirep::factory()->create([
        'user_id' => $this->owner->id,
        'state'   => PirepState::IN_PROGRESS,
    ]);
});

it("blocks cancelling another pilot's pirep", function (): void {
    apiAs($this->attacker);

    $this->putJson('/api/pireps/'.$this->pirep->id.'/cancel')->assertForbidden();
    $this->deleteJson('/api/pireps/'.$this->pirep->id.'/cancel')->assertForbidden();

    expect($this->pirep->fresh()->state)->toBe(PirepState::IN_PROGRESS);
});

it("blocks writing comments, fields, route and finances on another pilot's pirep", function (): void {
    apiAs($this->attacker);
    $id = $this->pirep->id;

    $this->postJson('/api/pireps/'.$id.'/comments', ['comment' => 'x'])->assertForbidden();
    $this->postJson('/api/pireps/'.$id.'/fields', ['fields' => ['a' => 'b']])->assertForbidden();
    $this->postJson('/api/pireps/'.$id.'/finances/recalculate')->assertForbidden();
    $this->deleteJson('/api/pireps/'.$id.'/route')->assertForbidden();
});

it('still lets the owner cancel and delete the route of their pirep', function (): void {
    Acars::factory()->create(['pirep_id' => $this->pirep->id, 'type' => AcarsType::ROUTE]);
    apiAs($this->owner);

    $this->deleteJson('/api/pireps/'.$this->pirep->id.'/route')->assertOk();
    $this->putJson('/api/pireps/'.$this->pirep->id.'/cancel')->assertOk();

    expect($this->pirep->fresh()->state)->toBe(PirepState::CANCELLED)
        ->and(Acars::where('pirep_id', $this->pirep->id)->count())->toBe(0);
});

it('blocks adding or removing bids for another user', function (): void {
    $flight = Flight::factory()->create(['airline_id' => $this->owner->airline_id]);
    apiAs($this->attacker);

    $this->putJson('/api/user/bids', ['id' => $this->owner->id, 'flight_id' => $flight->id])->assertForbidden();
    $this->postJson('/api/users/'.$this->owner->id.'/bids', ['id' => $this->owner->id, 'flight_id' => $flight->id])->assertForbidden();
    expect(Bid::where('user_id', $this->owner->id)->count())->toBe(0);

    Bid::create(['user_id' => $this->owner->id, 'flight_id' => $flight->id]);
    $this->deleteJson('/api/user/bids', ['id' => $this->owner->id, 'flight_id' => $flight->id])->assertForbidden();
    expect(Bid::where('user_id', $this->owner->id)->count())->toBe(1);
});

it('scopes simbrief briefings to their owner', function (): void {
    Storage::fake();
    Storage::put('ofp/victim.json', '{"secret":true}');
    $flight = Flight::factory()->create();
    $briefing = SimBrief::factory()->create([
        'user_id'       => $this->owner->id,
        'flight_id'     => $flight->id,
        'ofp_json_path' => 'ofp/victim.json',
    ]);

    apiAs($this->attacker);
    $this->getJson('/api/flights/'.$flight->id.'/briefing')->assertNotFound();

    $this->actingAs($this->attacker);
    $this->get('/simbrief/'.$briefing->id.'/cancel');
    expect(SimBrief::find($briefing->id))->not->toBeNull();

    $this->get('/simbrief/'.$briefing->id.'/generate_new');
    expect(SimBrief::find($briefing->id))->not->toBeNull();

    $this->get('/simbrief/'.$briefing->id)->assertRedirect(route('frontend.flights.index'));
});

it('requires the pirep edit permission to accept or reject in the admin', function (string $action): void {
    Filament::setCurrentPanel('admin');
    Permission::firstOrCreate(['name' => 'view:pirep', 'guard_name' => 'web']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view:pirep');

    $pirep = Pirep::factory()->create(['state' => PirepState::PENDING]);

    $this->actingAs($viewer->fresh());

    // Raw Livewire calls, the way a crafted request would bypass the hidden button.
    livewire(ViewPirep::class, ['record' => $pirep->id])
        ->assertActionHidden($action)
        ->call('mountAction', $action)
        ->call('callMountedAction');

    expect($pirep->fresh()->state)->toBe(PirepState::PENDING);
})->with(['accept', 'reject']);

it('ignores file owners that are not a known model', function (): void {
    File::factory()->create([
        'ref_model_type' => Collection::class,
        'ref_model_id'   => '1',
    ]);

    $this->actingAs($this->attacker)->get('/downloads')->assertOk();
});

it("does not load or attach another pilot's simbrief briefing to a pirep", function (): void {
    $briefing = SimBrief::factory()->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->attacker)
        ->get('/pireps/create?sb_id='.$briefing->id)
        ->assertNotFound();

    $pirep = Pirep::factory()->create(['user_id' => $this->attacker->id, 'state' => PirepState::IN_PROGRESS]);
    app(PirepService::class)->file($pirep, ['simbrief_id' => $briefing->id]);

    expect($briefing->fresh()->pirep_id)->toBeNull();
});

it('ignores state and id when a pilot updates their own pirep through the api', function (): void {
    apiAs($this->owner);

    $this->patchJson('/api/pireps/'.$this->pirep->id, [
        'state' => PirepState::ACCEPTED,
        'id'    => 'hijacked-id',
        'notes' => 'cruise',
    ])->assertOk();

    $pirep = $this->pirep->fresh();
    expect($pirep)->not->toBeNull()
        ->and($pirep->state)->toBe(PirepState::IN_PROGRESS)
        ->and($pirep->notes)->toBe('cruise');
});

it('escapes pilot notes in the admin pirep view', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $pirep = Pirep::factory()->create([
        'state' => PirepState::PENDING,
        'notes' => '<script>alert(1)</script>',
    ]);

    $this->actingAs(createAdminUser())
        ->get(PirepResource::getUrl('view', ['record' => $pirep]))
        ->assertSuccessful()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('only regenerates the api key on a post', function (): void {
    $old = $this->owner->api_key;
    $this->actingAs($this->owner);

    // A GET (e.g. a forged link or image) must not rotate the key.
    $this->get('/profile/regen_apikey');
    expect($this->owner->fresh()->api_key)->toBe($old);

    $this->post(route('frontend.profile.regen_apikey'))->assertRedirect();
    expect($this->owner->fresh()->api_key)->not->toBe($old);
});

it('keeps the owner when a pilot files their pirep with another user_id', function (): void {
    $briefing = SimBrief::factory()->create(['user_id' => $this->attacker->id]);
    apiAs($this->owner);

    $this->postJson('/api/pireps/'.$this->pirep->id.'/file', [
        'distance'    => 100,
        'flight_time' => 60,
        'user_id'     => $this->attacker->id,
        'simbrief_id' => $briefing->id,
    ])->assertOk();

    expect($this->pirep->fresh()->user_id)->toBe($this->owner->id)
        ->and($briefing->fresh()->pirep_id)->toBeNull();
});
