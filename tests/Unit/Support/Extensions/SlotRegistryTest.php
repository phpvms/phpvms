<?php

declare(strict_types=1);

use App\Enums\Slot;
use App\Support\Extensions\SlotRegistry;

test('registering with a Slot case stores the same entry as the equivalent string', function (): void {
    $withEnum = new SlotRegistry()->register(['slot' => Slot::FlightsDetailActions, 'component' => 'X']);
    $withString = new SlotRegistry()->register(['slot' => 'flights.detail.actions', 'component' => 'X']);

    expect($withEnum->all())->toBe($withString->all())
        ->and(json_encode($withEnum->all()))->toBe(json_encode($withString->all()));
});
