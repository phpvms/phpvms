<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Enums\PirepState;
use App\Features\Pireps\Livewire\PirepForm;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\SimBrief;
use App\Models\User;
use Livewire\Livewire;

/**
 * 8.1: filing from a SimBrief plan, and the web controller's store/update going through the shared action.
 */
beforeEach(function (): void {
    $this->fx = pirepFormFixture();
    $this->withoutVite();
    $this->actingAs($this->fx['pilot']);
});

function simbriefPlan(array $fx, array $attributes = []): SimBrief
{
    $flight = Flight::factory()->create([
        'airline_id'     => $fx['airline']->id,
        'dpt_airport_id' => $fx['dpt']->id,
        'arr_airport_id' => $fx['arr']->id,
        'flight_number'  => 2468,
    ]);

    return SimBrief::factory()->create([
        'user_id'     => $fx['pilot']->id,
        'flight_id'   => $flight->id,
        'aircraft_id' => $fx['aircraftA']->id,
        'fare_data'   => json_encode([
            array_merge($fx['fares'][1]->only(['id', 'code', 'name', 'type', 'capacity']), ['count' => 55]),
        ]),
        ...$attributes,
    ]);
}

it('fills the form from a SimBrief plan with its aircraft and fare counts', function (): void {
    $plan = simbriefPlan($this->fx);

    Livewire::test(PirepForm::class, ['simbriefId' => (string) $plan->id])
        ->assertSet('form.flight_number', '2468')
        ->assertSet('form.aircraft_id', (string) $this->fx['aircraftA']->id)
        ->assertSet('form.fares', [$this->fx['fares'][1]->id => '55'])
        ->assertSee('N-AAA')
        ->assertDontSee('N-BBB')
        ->assertDontSee('Search by ICAO or name');
});

it('files from a SimBrief plan and attaches it to the PIREP', function (): void {
    $plan = simbriefPlan($this->fx);

    Livewire::test(PirepForm::class, ['simbriefId' => (string) $plan->id])
        ->set('form.hours', '2')
        ->set('form.minutes', '0')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $pirep = Pirep::query()->firstOrFail();

    expect($pirep->aircraft_id)->toBe($this->fx['aircraftA']->id)
        ->and($plan->refresh()->pirep_id)->toBe($pirep->id)
        ->and($plan->flight_id)->toBeNull();
});

it("does not open another pilot's SimBrief plan", function (): void {
    $plan = simbriefPlan($this->fx, ['user_id' => User::factory()->create()->id]);

    Livewire::test(PirepForm::class, ['simbriefId' => (string) $plan->id])->assertNotFound();
});

it('files a PIREP through the controller with the same action', function (): void {
    updateSetting('general.theme', 'seven');

    $this->post(route('frontend.pireps.store'), [
        'airline_id'                      => $this->fx['airline']->id,
        'flight_number'                   => '31',
        'flight_type'                     => 'J',
        'hours'                           => '1',
        'minutes'                         => '10',
        'dpt_airport_id'                  => $this->fx['dpt']->id,
        'arr_airport_id'                  => $this->fx['arr']->id,
        'aircraft_id'                     => $this->fx['aircraftA']->id,
        'fare_'.$this->fx['fares'][1]->id => '12',
        'submit'                          => 'Save',
    ])->assertRedirect();

    $pirep = Pirep::query()->with('fares')->firstOrFail();
    expect($pirep->state)->toBe(PirepState::DRAFT)
        ->and($pirep->flight_time)->toBe(70)
        ->and($pirep->fares->pluck('count', 'code')->all())->toBe([$this->fx['fares'][1]->code => 12, $this->fx['fares'][2]->code => 0]);

    $this->patch(route('frontend.pireps.update', [$pirep->id]), [
        'airline_id'     => $this->fx['airline']->id,
        'flight_number'  => '31',
        'flight_type'    => 'J',
        'hours'          => '2',
        'minutes'        => '0',
        'block_fuel'     => '1000',
        'fuel_used'      => '900',
        'dpt_airport_id' => $this->fx['dpt']->id,
        'arr_airport_id' => $this->fx['arr']->id,
        'aircraft_id'    => $this->fx['aircraftA']->id,
        'submit'         => 'Delete',
    ])->assertRedirect(route('frontend.pireps.index'));

    expect(Pirep::query()->find($pirep->id))->toBeNull();
});

it('refuses a submitted PIREP from the controller when the pilot is not at the departure airport', function (): void {
    updateSetting('general.theme', 'seven');
    updateSetting('pilots.only_flights_from_current', true);
    $this->fx['pilot']->update(['curr_airport_id' => $this->fx['arr']->id]);

    $this->from(route('frontend.pireps.create'))->post(route('frontend.pireps.store'), [
        'airline_id'     => $this->fx['airline']->id,
        'flight_number'  => '31',
        'flight_type'    => 'J',
        'hours'          => '1',
        'minutes'        => '10',
        'dpt_airport_id' => $this->fx['dpt']->id,
        'arr_airport_id' => $this->fx['arr']->id,
        'aircraft_id'    => $this->fx['aircraftA']->id,
        'submit'         => 'Submit',
    ])->assertRedirect(route('frontend.pireps.create'));

    expect(Pirep::query()->count())->toBe(0);
});
