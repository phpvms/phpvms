<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Enums\PirepState;
use App\Features\Pireps\Livewire\PirepForm;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\PirepFare;
use App\Models\PirepField;
use App\Models\PirepFieldValue;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->fx = pirepFormFixture();
    $this->withoutVite();
    $this->actingAs($this->fx['pilot']);
});

function validFilingInput(array $fx): array
{
    return [
        'form.airline_id'     => (string) $fx['airline']->id,
        'form.flight_number'  => '1234',
        'form.flight_type'    => 'J',
        'form.hours'          => '1',
        'form.minutes'        => '30',
        'form.level'          => '350',
        'form.dpt_airport_id' => $fx['dpt']->id,
        'form.arr_airport_id' => $fx['arr']->id,
        'form.aircraft_id'    => (string) $fx['aircraftA']->id,
        'form.block_fuel'     => '5000',
        'form.fuel_used'      => '4200',
        'form.route'          => 'DCT',
        'form.notes'          => 'Smooth',
    ];
}

it('lists the aircraft the pilot may fly, grouped by subfleet type', function (): void {
    $options = Livewire::test(PirepForm::class)->get('aircraftOptions');

    expect(array_values(array_filter(array_keys($options))))->toBe(['A320', 'B738'])
        ->and(array_keys($options['A320']))->toBe([$this->fx['aircraftA']->id])
        ->and(array_keys($options['B738']))->toBe([$this->fx['aircraftB']->id]);
});

it('refreshes the fares when the aircraft changes', function (): void {
    $fares = $this->fx['fares'];

    $component = Livewire::test(PirepForm::class)
        ->set('form.aircraft_id', (string) $this->fx['aircraftA']->id)
        ->assertSet('form.fares', [$fares[1]->id => '', $fares[2]->id => ''])
        ->set('form.fares.'.$fares[1]->id, '120')
        ->set('form.fares.'.$fares[2]->id, '20');

    // B keeps the Fare Bravo count it shares with A, drops Fare Alpha, and starts Fare Charlie blank.
    $component
        ->set('form.aircraft_id', (string) $this->fx['aircraftB']->id)
        ->assertSet('form.fares', [$fares[2]->id => '20', $fares[3]->id => '']);

    // Clearing the aircraft clears the fares.
    $component->set('form.aircraft_id', '')->assertSet('form.fares', []);
});

it('validates a submission', function (): void {
    Livewire::test(PirepForm::class)
        ->call('submit')
        ->assertHasErrors([
            'form.airline_id'     => 'required',
            'form.flight_number'  => 'required',
            'form.dpt_airport_id' => 'required',
            'form.arr_airport_id' => 'required',
            'form.aircraft_id'    => 'required',
            'form.hours'          => 'required',
            'form.minutes'        => 'required',
        ])
        ->assertNoRedirect();

    expect(Pirep::query()->count())->toBe(0);
});

it('rejects bad numbers and negative fares', function (): void {
    $fares = $this->fx['fares'];

    Livewire::test(PirepForm::class)
        ->set(validFilingInput($this->fx))
        ->set('form.flight_number', '123456')
        ->set('form.hours', 'x')
        ->set('form.block_fuel', 'lots')
        ->set('form.fares.'.$fares[1]->id, '-1')
        ->call('submit')
        ->assertHasErrors(['form.flight_number', 'form.hours', 'form.block_fuel', 'form.fares.'.$fares[1]->id]);
});

it('saves a draft with only the four required fields', function (): void {
    Livewire::test(PirepForm::class)
        ->set('form.airline_id', (string) $this->fx['airline']->id)
        ->set('form.flight_number', '77')
        ->set('form.dpt_airport_id', $this->fx['dpt']->id)
        ->set('form.arr_airport_id', $this->fx['arr']->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $pirep = Pirep::query()->firstOrFail();
    expect($pirep->state)->toBe(PirepState::DRAFT)
        ->and($pirep->user_id)->toBe($this->fx['pilot']->id)
        ->and($pirep->flight_number)->toBe('77');
});

it('submits a PIREP with its fares and custom fields', function (): void {
    $fares = $this->fx['fares'];
    PirepField::query()->create(['name' => 'Landing Notes', 'slug' => 'landing-notes', 'required' => false, 'pirep_source' => 0]);

    Livewire::test(PirepForm::class)
        ->set(validFilingInput($this->fx))
        ->set('form.fares.'.$fares[1]->id, '120')
        ->set('form.fares.'.$fares[2]->id, '')
        ->set('form.fields.landing-notes', 'Firm')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $pirep = Pirep::query()->with('fares')->firstOrFail();

    expect($pirep->state)->not->toBe(PirepState::DRAFT)
        ->and($pirep->flight_time)->toBe(90)
        ->and($pirep->level)->toBe(350)
        ->and($pirep->notes)->toBe('Smooth')
        ->and($pirep->fares->pluck('count', 'code')->all())->toBe([$fares[1]->code => 120, $fares[2]->code => 0])
        ->and(PirepFieldValue::query()->where('pirep_id', $pirep->id)->where('slug', 'landing-notes')->value('value'))->toBe('Firm');
});

it('requires a required custom field to submit but not to save', function (): void {
    PirepField::query()->create(['name' => 'Callsign', 'slug' => 'callsign', 'required' => true, 'pirep_source' => 0]);

    Livewire::test(PirepForm::class)
        ->set(validFilingInput($this->fx))
        ->call('submit')
        ->assertHasErrors(['form.fields.callsign' => 'required'])
        ->call('save')
        ->assertHasNoErrors();

    expect(Pirep::query()->count())->toBe(1);
});

it('shows a filing rule as an error and files nothing', function (): void {
    updateSetting('pilots.only_flights_from_current', true);
    $this->fx['pilot']->update(['curr_airport_id' => $this->fx['arr']->id]);

    Livewire::test(PirepForm::class)
        ->set(validFilingInput($this->fx))
        ->call('submit')
        ->assertHasErrors('filing')
        ->assertNoRedirect();

    expect(Pirep::query()->count())->toBe(0);
});

it('does not file the same PIREP twice', function (): void {
    $component = Livewire::test(PirepForm::class)->set(validFilingInput($this->fx));
    $component->call('submit')->assertHasNoErrors();

    Livewire::test(PirepForm::class)
        ->set(validFilingInput($this->fx))
        ->call('submit')
        ->assertHasErrors('filing')
        ->assertNoRedirect();

    expect(Pirep::query()->count())->toBe(1);
});

it('refuses manual filing when it is disabled', function (): void {
    updateSetting('pireps.disable_manual', true);

    Livewire::test(PirepForm::class)->assertStatus(405);
});

it('starts from a flight and keeps its route fixed', function (): void {
    $flight = Flight::factory()->create([
        'airline_id'     => $this->fx['airline']->id,
        'dpt_airport_id' => $this->fx['dpt']->id,
        'arr_airport_id' => $this->fx['arr']->id,
        'flight_number'  => 4321,
        'route'          => 'DCT FIX',
    ]);
    $other = Airport::factory()->create();

    Livewire::test(PirepForm::class, ['flightId' => $flight->id])
        ->assertSet('form.flight_number', '4321')
        ->assertSet('form.route', 'DCT FIX')
        ->assertSet('form.dpt_airport_id', $this->fx['dpt']->id)
        ->set('form.dpt_airport_id', $other->id)
        ->set('form.aircraft_id', (string) $this->fx['aircraftA']->id)
        ->set('form.hours', '1')
        ->set('form.minutes', '0')
        ->call('save');

    $pirep = Pirep::query()->firstOrFail();
    expect($pirep->dpt_airport_id)->toBe($this->fx['dpt']->id)
        ->and($pirep->flight_id)->toBe($flight->id);
});

it('searches airports from two characters when the route is open', function (): void {
    $match = Airport::factory()->create(['icao' => 'KJFK', 'name' => 'Kennedy']);

    $component = Livewire::test(PirepForm::class)->set('dptSearch', 'K');
    expect($component->get('dptResults'))->toBe([]);

    $component->set('dptSearch', 'KJ');
    expect(collect($component->get('dptResults'))->pluck('label')->all())->toContain('KJFK - Kennedy');

    $component
        ->call('chooseAirport', 'dpt', $match->id)
        ->assertSet('form.dpt_airport_id', $match->id)
        ->assertSet('dptSearch', '')
        ->call('chooseAirport', 'sideways', $match->id)
        ->assertStatus(422);
});

function filedPirep(array $fx, array $attributes = []): Pirep
{
    return Pirep::factory()->create([
        'user_id'        => $fx['pilot']->id,
        'airline_id'     => $fx['airline']->id,
        'aircraft_id'    => $fx['aircraftA']->id,
        'dpt_airport_id' => $fx['dpt']->id,
        'arr_airport_id' => $fx['arr']->id,
        'flight_number'  => '555',
        'flight_time'    => 125,
        'state'          => PirepState::DRAFT,
        ...$attributes,
    ]);
}

it('edits a draft and keeps its saved values', function (): void {
    $pirep = filedPirep($this->fx, ['notes' => 'Before']);

    Livewire::test(PirepForm::class, ['pirepId' => $pirep->id])
        ->assertSet('form.flight_number', '555')
        ->assertSet('form.hours', '2')
        ->assertSet('form.minutes', '5')
        ->set('form.notes', 'After')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('frontend.pireps.show', [$pirep->id]));

    expect($pirep->refresh()->notes)->toBe('After')
        ->and($pirep->flight_time)->toBe(125)
        ->and($pirep->state)->toBe(PirepState::DRAFT);
});

it('submits a draft from the edit form', function (): void {
    $pirep = filedPirep($this->fx);

    Livewire::test(PirepForm::class, ['pirepId' => $pirep->id])
        ->call('submit')
        ->assertHasNoErrors();

    expect($pirep->refresh()->state)->not->toBe(PirepState::DRAFT);
});

it('keeps the fares entered earlier when editing', function (): void {
    $pirep = filedPirep($this->fx);
    $fares = $this->fx['fares'];
    PirepFare::query()->create(['pirep_id' => $pirep->id, 'fare_id' => null, 'code' => $fares[1]->code, 'name' => $fares[1]->name, 'count' => 33]);

    Livewire::test(PirepForm::class, ['pirepId' => $pirep->id])
        ->assertSet('form.fares', [$fares[1]->id => '33', $fares[2]->id => ''])
        ->set('form.aircraft_id', (string) $this->fx['aircraftB']->id)
        ->assertSet('form.fares', [$fares[2]->id => '', $fares[3]->id => '']);
});

it('deletes a draft after confirmation', function (): void {
    $pirep = filedPirep($this->fx);

    Livewire::test(PirepForm::class, ['pirepId' => $pirep->id])
        ->call('delete')
        ->assertRedirect(route('frontend.pireps.index'));

    expect(Pirep::query()->find($pirep->id))->toBeNull();
});

it('cannot save, submit or delete a read-only PIREP', function (): void {
    $pirep = filedPirep($this->fx, ['state' => PirepState::ACCEPTED, 'notes' => 'Before']);

    Livewire::test(PirepForm::class, ['pirepId' => $pirep->id])->set('form.notes', 'After')->call('save')->assertForbidden();
    Livewire::test(PirepForm::class, ['pirepId' => $pirep->id])->call('submit')->assertForbidden();
    Livewire::test(PirepForm::class, ['pirepId' => $pirep->id])->call('delete')->assertForbidden();

    $pirep->refresh();
    expect($pirep->notes)->toBe('Before')
        ->and($pirep->state)->toBe(PirepState::ACCEPTED);
});

it("does not open another pilot's PIREP", function (): void {
    $pirep = filedPirep($this->fx, ['user_id' => User::factory()->create()->id]);

    Livewire::test(PirepForm::class, ['pirepId' => $pirep->id])->assertForbidden();
    Livewire::test(PirepForm::class, ['pirepId' => 'missing'])->assertNotFound();
});
