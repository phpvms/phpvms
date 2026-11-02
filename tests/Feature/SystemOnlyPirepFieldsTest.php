<?php

declare(strict_types=1);

use App\Enums\PirepFieldSource;
use App\Enums\PirepState;
use App\Filament\Resources\PirepFields\Pages\ManagePirepFields;
use App\Models\Pirep;
use App\Models\PirepField;
use App\Models\PirepFieldValue;
use App\Models\User;
use App\Services\PirepService;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\CreateAction;
use Igaster\LaravelTheme\Facades\Theme;
use Livewire\Livewire;

/**
 * A system-only PIREP field is set by server code and never from pilot input.
 */
beforeEach(function (): void {
    $this->pilot = User::factory()->create();
    $this->pirep = Pirep::factory()->create([
        'user_id' => $this->pilot->id,
        'state'   => PirepState::IN_PROGRESS,
    ]);

    PirepField::create([
        'name'         => 'Measured Value',
        'pirep_source' => PirepFieldSource::BOTH,
        'system_only'  => true,
    ]);

    // Server code (an addon, a listener) writes the value.
    serverSetsField($this->pirep, 'Measured Value', '42');
});

function serverSetsField(Pirep $pirep, string $name, string $value): void
{
    app(PirepService::class)->updateCustomFields($pirep->id, [
        new PirepFieldValue(['name' => $name, 'value' => $value, 'source' => PirepFieldSource::ACARS]),
    ]);
}

function usePilotForm(): void
{
    updateSetting('pilots.only_flights_from_current', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    Theme::set('seven');
    updateSetting('general.theme', 'seven');
}

function measuredValues(Pirep $pirep): array
{
    return PirepFieldValue::where('pirep_id', $pirep->id)
        ->where('slug', 'measured-value')
        ->pluck('value')
        ->all();
}

it('lets server code set a system-only field', function (): void {
    expect(measuredValues($this->pirep))->toBe(['42']);

    serverSetsField($this->pirep, 'Measured Value', '55');

    expect(measuredValues($this->pirep))->toBe(['55']);
});

it('ignores a system-only field posted to the fields endpoint', function (string $key): void {
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$this->pirep->id.'/fields', [
        'fields' => [$key => '100', 'Departure Gate' => 'G26'],
    ])->assertOk();

    expect(measuredValues($this->pirep))->toBe(['42'])
        ->and(PirepFieldValue::where('pirep_id', $this->pirep->id)->where('slug', 'departure-gate')->value('value'))
        ->toBe('G26');
})->with([
    'by name' => 'Measured Value',
    'by slug' => 'measured-value',
    'by case' => 'MEASURED VALUE',
]);

it('protects a system-only field whose slug is falsy', function (): void {
    PirepField::create(['name' => '0', 'pirep_source' => PirepFieldSource::ACARS, 'system_only' => true]);
    serverSetsField($this->pirep, '0', 'measured');
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$this->pirep->id.'/fields', [
        'fields' => ['0' => 'forged'],
    ])->assertOk();

    expect(PirepFieldValue::where('pirep_id', $this->pirep->id)->where('slug', '0')->pluck('value')->all())
        ->toBe(['measured']);
});

it('ignores a system-only field sent with an update', function (): void {
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$this->pirep->id.'/update', [
        'fields' => ['Measured Value' => '100'],
    ])->assertOk();

    expect(measuredValues($this->pirep))->toBe(['42']);
});

it('ignores a system-only field sent when filing', function (): void {
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$this->pirep->id.'/file', [
        'distance'    => 100,
        'flight_time' => 60,
        'fields'      => ['measured-value' => '100'],
    ])->assertOk();

    expect(measuredValues($this->pirep))->toBe(['42']);
});

it('keeps system-only fields off the PIREP form and ignores them when submitted', function (): void {
    usePilotForm();

    $this->actingAs($this->pilot)
        ->get(route('frontend.pireps.create'))
        ->assertSuccessful()
        ->assertDontSee('Measured Value');

    $this->actingAs($this->pilot)->post(route('frontend.pireps.store'), [
        'airline_id'     => $this->pirep->airline_id,
        'aircraft_id'    => $this->pirep->aircraft_id,
        'flight_number'  => 100,
        'dpt_airport_id' => $this->pirep->dpt_airport_id,
        'arr_airport_id' => $this->pirep->arr_airport_id,
        'hours'          => 1,
        'minutes'        => 0,
        'block_fuel'     => 100,
        'fuel_used'      => 50,
        'submit'         => 'save',
        'measured-value' => '100',
    ])->assertRedirect();

    $manual = Pirep::where('flight_number', '100')->latest()->first();
    expect($manual)->not->toBeNull()
        ->and(measuredValues($manual))->toBe([]);
});

it('keeps system-only fields off the edit form but shows them on the report', function (): void {
    usePilotForm();
    $draft = Pirep::factory()->create(['user_id' => $this->pilot->id, 'state' => PirepState::DRAFT]);
    serverSetsField($draft, 'Measured Value', '42');
    serverSetsField($draft, 'Departure Gate', 'G26');

    $this->actingAs($this->pilot)
        ->get(route('frontend.pireps.edit', $draft->id))
        ->assertSuccessful()
        ->assertDontSee('Measured Value')
        ->assertSee('Departure Gate');

    $this->actingAs($this->pilot)
        ->get(route('frontend.pireps.show', $draft->id))
        ->assertSuccessful()
        ->assertSee('Measured Value');
});

it('does not require a required system-only field on the PIREP form', function (): void {
    usePilotForm();
    PirepField::whereSlug('measured-value')->update(['required' => true]);
    $form = [
        'airline_id'     => $this->pirep->airline_id,
        'aircraft_id'    => $this->pirep->aircraft_id,
        'flight_number'  => 200,
        'dpt_airport_id' => $this->pirep->dpt_airport_id,
        'arr_airport_id' => $this->pirep->arr_airport_id,
        'hours'          => 1,
        'minutes'        => 0,
        'block_fuel'     => 100,
        'fuel_used'      => 50,
        'submit'         => 'submit',
    ];

    $this->actingAs($this->pilot)
        ->post(route('frontend.pireps.store'), $form)
        ->assertSessionHasNoErrors();

    $draft = Pirep::factory()->create(['user_id' => $this->pilot->id, 'state' => PirepState::DRAFT]);

    $this->actingAs($this->pilot)
        ->put(route('frontend.pireps.update', $draft->id), $form)
        ->assertSessionHasNoErrors();
});

it('still saves fields that are not system-only', function (): void {
    PirepField::query()->update(['system_only' => false]);
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$this->pirep->id.'/fields', [
        'fields' => ['Measured Value' => '100'],
    ])->assertOk();

    expect(measuredValues($this->pirep))->toBe(['100']);
});

it('lets an admin mark a field as system-only', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    Livewire::test(ManagePirepFields::class)
        ->callAction(CreateAction::class, [
            'name'         => 'Network Check',
            'pirep_source' => PirepFieldSource::ACARS->value,
            'system_only'  => true,
        ])
        ->assertHasNoActionErrors();

    expect(PirepField::where('slug', 'network-check')->value('system_only'))->toBeTrue();
});
