<?php

declare(strict_types=1);

require_once __DIR__.'/../../Airside/PirepForm/Support.php';

use App\Enums\PirepState;
use App\Features\Pireps\Actions\PirepFilingDenied;
use App\Features\Pireps\Actions\SaveManualPirep;
use App\Models\Pirep;

/**
 * @param  array<string, mixed> $fx
 * @return array<string, mixed>
 */
function manualPirepInput(array $fx): array
{
    return [
        'airline_id'     => $fx['airline']->id,
        'flight_number'  => '1234',
        'flight_type'    => 'J',
        'hours'          => 1,
        'minutes'        => 30,
        'level'          => 350,
        'dpt_airport_id' => $fx['dpt']->id,
        'arr_airport_id' => $fx['arr']->id,
        'aircraft_id'    => $fx['aircraftA']->id,
        'block_fuel'     => 5000,
        'fuel_used'      => 4200,
        'route'          => 'DCT',
    ];
}

it('applies the filing rules when a saved draft is submitted', function (): void {
    $fx = pirepFormFixture();
    $input = manualPirepInput($fx);

    $draft = app(SaveManualPirep::class)->create($fx['pilot'], $input, 'save');
    expect($draft->state)->toBe(PirepState::DRAFT);

    updateSetting('pilots.only_flights_from_current', true);
    $fx['pilot']->update(['curr_airport_id' => $fx['arr']->id]);

    expect(fn () => app(SaveManualPirep::class)->update($draft, $input, 'submit'))
        ->toThrow(PirepFilingDenied::class);

    expect($draft->refresh()->state)->toBe(PirepState::DRAFT);
});

it('submits a saved draft that passes the filing rules', function (): void {
    $fx = pirepFormFixture();
    $input = manualPirepInput($fx);

    $draft = app(SaveManualPirep::class)->create($fx['pilot'], $input, 'save');

    app(SaveManualPirep::class)->update($draft, $input, 'submit');

    expect($draft->refresh()->state)->not->toBe(PirepState::DRAFT);
});

it('denies submitting a draft when another PIREP already filed the same flight', function (): void {
    $fx = pirepFormFixture();
    $input = manualPirepInput($fx);

    $draft = app(SaveManualPirep::class)->create($fx['pilot'], $input, 'save');

    Pirep::factory()->create([
        'user_id'        => $fx['pilot']->id,
        'airline_id'     => $fx['airline']->id,
        'flight_number'  => '1234',
        'dpt_airport_id' => $fx['dpt']->id,
        'arr_airport_id' => $fx['arr']->id,
        'aircraft_id'    => $fx['aircraftA']->id,
        'state'          => PirepState::PENDING,
    ]);

    expect(fn () => app(SaveManualPirep::class)->update($draft, $input, 'submit'))
        ->toThrow(PirepFilingDenied::class, 'This PIREP has already been filed.');
});
