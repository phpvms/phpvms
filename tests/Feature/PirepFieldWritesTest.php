<?php

declare(strict_types=1);

use App\Enums\PirepFieldSource;
use App\Enums\PirepState;
use App\Models\Pirep;
use App\Models\PirepField;
use App\Models\PirepFieldValue;
use App\Models\User;
use App\Services\PirepService;

/**
 * The ACARS API can change a PIREP's custom fields until the PIREP is filed,
 * and not after: values set at filing stay as they are during review.
 */
beforeEach(function (): void {
    $this->pilot = User::factory()->create();
});

function pilotPirep(User $pilot, PirepState $state): Pirep
{
    return Pirep::factory()->create(['user_id' => $pilot->id, 'state' => $state]);
}

function setFieldAsServer(Pirep $pirep, string $name, string $value): void
{
    app(PirepService::class)->updateCustomFields($pirep->id, [
        new PirepFieldValue(['name' => $name, 'value' => $value, 'source' => PirepFieldSource::ACARS]),
    ]);
}

function fieldValue(Pirep $pirep, string $slug): ?string
{
    return PirepFieldValue::where('pirep_id', $pirep->id)->where('slug', $slug)->value('value');
}

it('lets the api change fields until the pirep is filed', function (PirepState $state): void {
    $pirep = pilotPirep($this->pilot, $state);
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$pirep->id.'/fields', [
        'fields' => ['Departure Gate' => 'G26'],
    ])->assertOk();

    expect(fieldValue($pirep, 'departure-gate'))->toBe('G26');
})->with([
    'in progress' => PirepState::IN_PROGRESS,
    'paused'      => PirepState::PAUSED,
    'draft'       => PirepState::DRAFT,
]);

it('refuses to change fields through the fields endpoint once the pirep is filed', function (PirepState $state): void {
    $pirep = pilotPirep($this->pilot, $state);
    setFieldAsServer($pirep, 'Network Check', 'failed');
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$pirep->id.'/fields', [
        'fields' => ['Network Check' => 'passed'],
    ])->assertStatus(400);

    expect(fieldValue($pirep, 'network-check'))->toBe('failed');
})->with([
    'pending'  => PirepState::PENDING,
    'accepted' => PirepState::ACCEPTED,
    'rejected' => PirepState::REJECTED,
]);

it('ignores fields sent with an update once the pirep is filed, but applies the rest', function (): void {
    $pirep = pilotPirep($this->pilot, PirepState::PENDING);
    setFieldAsServer($pirep, 'Network Check', 'failed');
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$pirep->id.'/update', [
        'notes'  => 'Smooth flight',
        'fields' => ['Network Check' => 'passed'],
    ])->assertOk();

    expect(fieldValue($pirep, 'network-check'))->toBe('failed')
        ->and($pirep->fresh()->notes)->toBe('Smooth flight');
});

it('saves the fields sent with the file request itself', function (): void {
    $pirep = pilotPirep($this->pilot, PirepState::IN_PROGRESS);
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$pirep->id.'/file', [
        'distance'    => 100,
        'flight_time' => 60,
        'fields'      => ['Landing Rate' => '-120'],
    ])->assertOk();

    expect(fieldValue($pirep, 'landing-rate'))->toBe('-120');
});

it('still lets server code change fields after the pirep is filed', function (): void {
    $pirep = pilotPirep($this->pilot, PirepState::PENDING);

    setFieldAsServer($pirep, 'Network Check', 'failed');
    setFieldAsServer($pirep, 'Network Check', 'passed');

    expect(fieldValue($pirep, 'network-check'))->toBe('passed');
});

it('updates one value whatever casing or slug the field is sent under', function (): void {
    $pirep = pilotPirep($this->pilot, PirepState::IN_PROGRESS);
    apiAs($this->pilot);

    foreach (['Departure Gate' => 'G26', 'departure-gate' => 'G27', 'DEPARTURE GATE' => 'G28'] as $name => $value) {
        $this->postJson('/api/pireps/'.$pirep->id.'/fields', ['fields' => [$name => $value]])->assertOk();
    }

    expect(PirepFieldValue::where('pirep_id', $pirep->id)->where('slug', 'departure-gate')->pluck('value')->all())
        ->toBe(['G28']);
});

it('saves a manual field the pilot fills in on the web form', function (): void {
    PirepField::create(['name' => 'Remarks', 'pirep_source' => PirepFieldSource::MANUAL]);
    $pirep = pilotPirep($this->pilot, PirepState::DRAFT);

    $this->actingAs($this->pilot)->put(route('frontend.pireps.update', $pirep->id), [
        'airline_id'     => $pirep->airline_id,
        'flight_number'  => 100,
        'dpt_airport_id' => $pirep->dpt_airport_id,
        'arr_airport_id' => $pirep->arr_airport_id,
        'hours'          => 1,
        'minutes'        => 0,
        'block_fuel'     => 100,
        'fuel_used'      => 50,
        'submit'         => 'save',
        'remarks'        => 'Diverted for weather',
    ])->assertRedirect();

    expect(fieldValue($pirep, 'remarks'))->toBe('Diverted for weather');
});

it('keeps the name a field was first saved with', function (): void {
    $pirep = pilotPirep($this->pilot, PirepState::IN_PROGRESS);
    apiAs($this->pilot);

    $this->postJson('/api/pireps/'.$pirep->id.'/fields', ['fields' => ['Departure Gate' => 'G26']])->assertOk();
    $this->postJson('/api/pireps/'.$pirep->id.'/fields', ['fields' => ['departure-gate' => 'G27']])->assertOk();

    $value = PirepFieldValue::where('pirep_id', $pirep->id)->sole();
    expect($value->name)->toBe('Departure Gate')
        ->and($value->value)->toBe('G27')
        ->and($pirep->fresh()->field('Departure Gate'))->toBe('G27');
});

it('updates the oldest of the values older data already holds under one slug', function (): void {
    $pirep = pilotPirep($this->pilot, PirepState::IN_PROGRESS);
    // Inserted first, but created later: the row an unordered lookup finds.
    PirepFieldValue::create(['pirep_id' => $pirep->id, 'name' => 'departure-gate', 'value' => 'G2', 'source' => PirepFieldSource::ACARS]);
    $oldest = PirepFieldValue::create(['pirep_id' => $pirep->id, 'name' => 'Departure Gate', 'value' => 'G1', 'source' => PirepFieldSource::ACARS]);
    $oldest->forceFill(['created_at' => now()->subMinute()])->save();

    setFieldAsServer($pirep, 'Departure Gate', 'G3');

    expect($oldest->fresh()->value)->toBe('G3')
        ->and($pirep->fresh()->fields->firstWhere('slug', 'departure-gate')->value)->toBe('G3');
});
