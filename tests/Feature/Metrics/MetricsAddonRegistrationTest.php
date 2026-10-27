<?php

declare(strict_types=1);

use App\Support\Metrics\Facades\Metrics;
use Illuminate\Support\ServiceProvider;

/**
 * End-to-end verification of the addon registration path (tasks.md 5.2):
 * a metric registered from a provider's boot() is present once that boot()
 * has run, and absent otherwise -- the same structural disable-safety
 * app/Support/Skylight/WidgetRegistry.php documents for widgets. A disabled
 * addon's ServiceProvider::boot() never runs at all, so there is nothing
 * addon-specific to fake beyond "boot() ran" vs "boot() did not run": the
 * registry has no runtime enabled flag to bypass either way.
 */
final class FakeAddonMetricServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Metrics::registry()->register([
            'id'      => 'addon.fake_metric',
            'label'   => 'Fake Addon Metric',
            'resolve' => static fn (): int => 99,
        ]);
    }
}

it('registers an addon metric once its provider boots, like an enabled addon', function (): void {
    expect(Metrics::has('addon.fake_metric'))->toBeFalse();

    new FakeAddonMetricServiceProvider(app())->boot();

    expect(Metrics::has('addon.fake_metric'))->toBeTrue()
        ->and(Metrics::resolve('addon.fake_metric'))->toBe(99);
});

it('leaves an addon metric absent, with no dead entry, when its provider never boots', function (): void {
    // The disabled-addon case: FakeAddonMetricServiceProvider::boot() is
    // simply never called. Nothing in the registry references its id.
    expect(Metrics::has('addon.fake_metric'))->toBeFalse()
        ->and(Metrics::resolve('addon.fake_metric'))->toBeNull()
        ->and(collect(Metrics::all())->pluck('id'))->not->toContain('addon.fake_metric');
});

it('lets an addon override a first-party metric by registering the same id', function (): void {
    Metrics::registry()->register(['id' => 'pilots.total', 'label' => 'First Party', 'resolve' => fn (): int => 1]);

    expect(Metrics::registry()->get('pilots.total')['label'])->toBe('First Party');

    Metrics::registry()->register(['id' => 'pilots.total', 'label' => 'Addon Override', 'resolve' => fn (): int => 2]);

    expect(Metrics::registry()->get('pilots.total')['label'])->toBe('Addon Override')
        ->and(Metrics::resolve('pilots.total'))->toBe(2);
});
