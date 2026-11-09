<?php

declare(strict_types=1);

use App\Contracts\Metar as MetarProvider;
use App\Features\Tiles\Tiles;
use App\Models\Airport;
use App\Models\Rank;
use App\Models\User;
use Composer\Autoload\ClassLoader;
use Livewire\Livewire;
use Modules\PhpvmsWidgets\Livewire\RankProgress;
use Modules\PhpvmsWidgets\Livewire\Weather;
use Modules\PhpvmsWidgets\Providers\PhpvmsWidgetsServiceProvider;

/**
 * The addon installs disabled and the addon autoloader maps it at runtime, so
 * the test maps its namespace and registers its provider by hand.
 */
beforeEach(function (): void {
    /** @var ClassLoader $loader */
    $loader = require base_path('vendor/autoload.php');
    $loader->addPsr4('Modules\\PhpvmsWidgets\\', base_path('modules/phpvms-widgets'));

    app()->register(PhpvmsWidgetsServiceProvider::class);
});

it('registers every twin under a Livewire name that resolves, in the skylight default order', function (): void {
    $registry = app(Tiles::class);

    foreach ($registry->all() as $tile) {
        if ($tile->livewire === null) {
            continue;
        }

        expect(class_exists(app('livewire.factory')->resolveComponentClass((string) $tile->livewire)))->toBeTrue();
    }

    expect($registry->defaultLayout())->toBe([
        'grid'    => ['kpi-hours', 'kpi-flights', 'kpi-balance', 'rank'],
        'sidebar' => ['last-flight', 'weather'],
    ]);
});

it('computes progress to the next rank by hours', function (): void {
    $current = Rank::factory()->create(['name' => 'First Officer', 'hours' => 100]);
    Rank::factory()->create(['name' => 'Captain', 'hours' => 200]);
    $user = User::factory()->create(['rank_id' => $current->id, 'flight_time' => 50 * 60, 'transfer_time' => 50 * 60]);

    updateSetting('pilots.count_transfer_hours', false);
    expect(RankProgress::for($user->fresh()))->toBe(['from' => 'First Officer', 'to' => 'Captain', 'pct' => 25]);

    updateSetting('pilots.count_transfer_hours', true);
    expect(RankProgress::for($user->fresh())['pct'])->toBe(50);
});

it('reports the top rank as complete', function (): void {
    $top = Rank::factory()->create(['name' => 'Commander', 'hours' => 9000]);
    $user = User::factory()->create(['rank_id' => $top->id, 'flight_time' => 60]);

    expect(RankProgress::for($user->fresh()))->toBe(['from' => 'Commander', 'to' => null, 'pct' => 100]);
});

it('shows weather for the ICAO of the current airport, else home, unless the theme passes a station', function (): void {
    $this->mock(MetarProvider::class)->shouldReceive('metar')->andReturn('');
    $home = Airport::factory()->create(['id' => 'HOME1', 'icao' => 'KHOM']);
    $current = Airport::factory()->create(['id' => 'CURR1', 'icao' => 'KCUR']);

    $this->actingAs(User::factory()->create(['home_airport_id' => $home->id, 'curr_airport_id' => $current->id]));
    Livewire::test(Weather::class, ['lazy' => false])->assertSet('icao', 'KCUR');
    Livewire::test(Weather::class, ['lazy' => false, 'icao' => 'EGLL'])->assertSet('icao', 'EGLL');

    $this->actingAs(User::factory()->create(['home_airport_id' => $home->id, 'curr_airport_id' => null]));
    Livewire::test(Weather::class, ['lazy' => false])->assertSet('icao', 'KHOM');
});
