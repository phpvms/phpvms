<?php

use App\Enums\AcarsType;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepEvent;
use App\Services\PirepService;
use Illuminate\Database\QueryException;

it('restricts deleting an acars row referenced by a pirep event', function (): void {
    $pirep = Pirep::factory()->create();
    $acars = Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::FLIGHT_PATH]);

    PirepEvent::factory()->create([
        'pirep_id' => $pirep->id,
        'acars_id' => $acars->id,
    ]);

    expect(fn () => $acars->delete())->toThrow(QueryException::class);
});

it('upserts on pirep_id and client_event_id, keeping the latest write', function (): void {
    $pirep = Pirep::factory()->create();

    PirepEvent::query()->updateOrCreate(
        ['pirep_id' => $pirep->id, 'client_event_id' => 'evt-1'],
        ['category' => 'message', 'log' => 'first']
    );

    PirepEvent::query()->updateOrCreate(
        ['pirep_id' => $pirep->id, 'client_event_id' => 'evt-1'],
        ['category' => 'aircraft', 'log' => 'second']
    );

    $events = PirepEvent::where('pirep_id', $pirep->id)->where('client_event_id', 'evt-1')->get();

    expect($events)->toHaveCount(1);
    expect($events->first()->category)->toBe('aircraft');
    expect($events->first()->log)->toBe('second');
});

it('force-deletes a PIREP whose events reference telemetry without an FK error', function (): void {
    $pirep = Pirep::factory()->create();
    $acars = Acars::factory()->count(2)->create(['pirep_id' => $pirep->id, 'type' => AcarsType::FLIGHT_PATH]);

    foreach ($acars as $telemetry) {
        PirepEvent::factory()->create([
            'pirep_id' => $pirep->id,
            'acars_id' => $telemetry->id,
        ]);
    }

    app(PirepService::class)->delete($pirep);

    expect(PirepEvent::where('pirep_id', $pirep->id)->count())->toBe(0);
    expect(Acars::where('pirep_id', $pirep->id)->count())->toBe(0);
});
