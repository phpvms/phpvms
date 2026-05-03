<?php

use App\Models\FlightField;
use App\Models\Pirep;
use App\Models\PirepFieldValue;

/*
|--------------------------------------------------------------------------
| Trait Testing: HasSlug
|--------------------------------------------------------------------------
|
| While the tests below specifically use the FlightField model, the intent is to
| verify the global behavior of the HasSlug trait. By confirming the logic
| here, we ensure that any model using this trait—whether it's a FlightField,
| Page, or PirepField—will handle slugging.
|
*/

it('generates a slug from the name on creation', function () {
    $flightField = FlightField::create(['name' => 'Pest is Awesome']);

    expect($flightField->slug)->toBe('pest-is-awesome');
});

it('updates the slug when the name changes', function () {
    $flightField = FlightField::create(['name' => 'Old Title']);

    $flightField->update(['name' => 'New Title']);

    expect($flightField->refresh()->slug)->toBe('new-title');
});

it('does not change the slug if the name is unchanged', function () {
    $pirep = Pirep::factory()->create();
    $pirepFieldValue = PirepFieldValue::create([
        'pirep_id' => $pirep->id,
        'source'   => PirepSource::ACARS,
        'name'     => 'Fixed Title',
    ]);
    $originalSlug = $pirepFieldValue->slug;

    $pirepFieldValue->update(['value' => 12]);

    expect($pirepFieldValue->slug)->toBe($originalSlug);
});
