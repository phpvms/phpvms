<?php

declare(strict_types=1);

use App\Features\Addons\Support\OctaneReloader;
use App\Filament\Pages\Maintenance;
use App\Livewire\Filament\ClearCaches;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Mockery\MockInterface;

/**
 * Fakes every process and returns the artisan sub-commands it is asked to run.
 *
 * @return ArrayObject<int, string>
 */
function fakeArtisanProcesses(): ArrayObject
{
    /** @var ArrayObject<int, string> $ran */
    $ran = new ArrayObject();
    Process::fake(function (PendingProcess $process) use ($ran): ProcessResult {
        $ran[] = $process->command[2];

        return Process::result();
    });

    return $ran;
}

function swapLocal(bool $local): void
{
    app()->detectEnvironment(static fn (): string => $local ? 'local' : 'production');
}

it('hangs the addon cache rebuild on optimize and not on optimize:clear', function (): void {
    $optimize = new ReflectionProperty(ServiceProvider::class, 'optimizeCommands');
    $clear = new ReflectionProperty(ServiceProvider::class, 'optimizeClearCommands');

    expect($optimize->getValue())->toContain('addons:cache')
        ->and($clear->getValue())->not->toContain('addons:cache');
});

it('optimizes once and reloads once after clearing outside local', function (string $component): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    swapLocal(false);
    $ran = fakeArtisanProcesses();
    $this->mock(OctaneReloader::class, fn (MockInterface $mock) => $mock->shouldReceive('reload')->once());

    $test = Livewire::test($component);
    $component === Maintenance::class ? $test->callAction('clearCache') : $test->call('clear');

    $ran = $ran->getArrayCopy();
    expect(array_count_values($ran)['optimize'] ?? 0)->toBe(1)
        ->and(array_search('optimize', $ran, true))->toBeGreaterThan(array_search('optimize:clear', $ran, true));
})->with([Maintenance::class, ClearCaches::class]);

it('skips optimize and the reload in local', function (string $component): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    swapLocal(true);
    $ran = fakeArtisanProcesses();
    $this->mock(OctaneReloader::class, fn (MockInterface $mock) => $mock->shouldNotReceive('reload'));

    $test = Livewire::test($component);
    $component === Maintenance::class ? $test->callAction('clearCache') : $test->call('clear');

    expect($ran->getArrayCopy())->not->toContain('optimize');
})->with([Maintenance::class, ClearCaches::class]);

it('reloads workers once after the Optimize action', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
    fakeArtisanProcesses();
    $this->mock(OctaneReloader::class, fn (MockInterface $mock) => $mock->shouldReceive('reload')->once());

    Livewire::test(Maintenance::class)->callAction('optimizeApp');
});
