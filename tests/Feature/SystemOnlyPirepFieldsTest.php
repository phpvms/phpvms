<?php

declare(strict_types=1);

use App\Enums\PirepFieldSource;
use App\Enums\PirepState;
use App\Models\Pirep;
use App\Models\PirepField;
use App\Models\PirepFieldValue;
use App\Models\User;
use App\Services\PirepService;
use Igaster\LaravelTheme\Facades\Theme;

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
    app(PirepService::class)->updateCustomFields($this->pirep->id, [
        ['name' => 'Measured Value', 'value' => '42', 'source' => PirepFieldSource::ACARS],
    ]);
});

function measuredValues(Pirep $pirep): array
{
    return PirepFieldValue::where('pirep_id', $pirep->id)
        ->where('slug', 'measured-value')
        ->pluck('value')
        ->all();
}

it('lets server code set a system-only field', function (): void {
    expect(measuredValues($this->pirep))->toBe(['42']);

    app(PirepService::class)->updateCustomFields($this->pirep->id, [
        ['name' => 'Measured Value', 'value' => '55', 'source' => PirepFieldSource::ACARS],
    ]);

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
    updateSetting('pilots.only_flights_from_current', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    Theme::set('seven');
    updateSetting('general.theme', 'seven');

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

it('still saves fields that are not system-only', function (): void {
    PirepField::query()->update(['system_only' => false]);
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$this->pirep->id.'/fields', [
        'fields' => ['Measured Value' => '100'],
    ])->assertOk();

    expect(measuredValues($this->pirep))->toBe(['100']);
});
