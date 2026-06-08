<?php

declare(strict_types=1);

use App\Models\Addon;

it('addons:relink runs and reports success', function (): void {
    Addon::factory()->create(['name' => 'Awards', 'path' => base_path('modules/Awards'), 'enabled' => true]);

    $this->artisan('addons:relink')
        ->assertExitCode(0);
});
