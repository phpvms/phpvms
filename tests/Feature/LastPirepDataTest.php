<?php

declare(strict_types=1);

use App\Http\Data\LastPirepData;
use App\Models\Pirep;

/**
 * The Pirep airport relations use withDefault(), so a PIREP with no arrival
 * airport hands back an empty Airport, not null. That must map to null rather
 * than an AirportPointData with a null id.
 */
it('maps a missing airport to null', function (): void {
    $pirep = Pirep::factory()->create(['arr_airport_id' => '']);

    $data = LastPirepData::fromModel($pirep->fresh());

    expect($data->arr_airport)->toBeNull()
        ->and($data->dpt_airport?->id)->toBe($pirep->dpt_airport_id);
});
