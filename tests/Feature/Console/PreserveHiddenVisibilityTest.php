<?php

declare(strict_types=1);

use App\Models\Flight;
use Illuminate\Support\Facades\Artisan;

it('disables flights that were hidden but enabled', function (): void {
    $hiddenEnabled = Flight::factory()->create([
        'visible' => false,
        'enabled' => true,
    ]);

    $visibleEnabled = Flight::factory()->create([
        'visible' => true,
        'enabled' => true,
    ]);

    Artisan::call('phpvms:preserve-hidden-visibility');

    $hiddenEnabled->refresh();
    $visibleEnabled->refresh();

    expect($hiddenEnabled->enabled)->toBeFalse()
        ->and($visibleEnabled->enabled)->toBeTrue();
});
