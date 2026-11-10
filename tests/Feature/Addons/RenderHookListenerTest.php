<?php

declare(strict_types=1);

use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Fill;
use App\Features\Hooks\Points\RenderingPirepDetail;
use App\Models\Pirep;
use Composer\Autoload\ClassLoader;
use PhpvmsAddonFixture\Hooks\Briefing\AcmePirepFillListener;
use PhpvmsAddonFixture\Providers\AcmeServiceProvider;

it('routes an addon render hook listener to the hook dispatcher', function (): void {
    $loader = collect(spl_autoload_functions())->first(fn ($entry): bool => is_array($entry) && $entry[0] instanceof ClassLoader)[0];
    $loader->addPsr4('PhpvmsAddonFixture\\', realpath(__DIR__.'/../../fixtures/Addons/acme/app'));

    app()->register(AcmeServiceProvider::class);

    // Other installed addons (the symlinked acars plugin on a dev machine) may
    // listen on the same point, so look for the fixture's listener and fill only.
    expect(collect(Hooks::listenersFor(RenderingPirepDetail::class))->pluck('listener')->all())
        ->toContain(AcmePirepFillListener::class);

    $fills = collect(Hooks::filter(new RenderingPirepDetail(new Pirep())))
        ->filter(fn (Fill $fill): bool => $fill->kind === 'html' && str_contains((string) $fill->html, 'data-acme-fill'));

    expect($fills)->toHaveCount(1);
});
