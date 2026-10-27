<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Skylight\Facades\Skylight;
use Illuminate\Http\Request;

// The `skylight` Inertia prop (Skylight::toArray()) is the ONE catalog read path
// every consumer uses — the pilot dashboard board, and (per web-component-widget-
// floor D4) a future page editor's insert menu. This confirms a tag-kind widget,
// registered exactly as an addon's ServiceProvider::boot() would, reaches it.
it('shares a registered tag-kind widget in the skylight catalog', function (): void {
    Skylight::widgets()->register([
        'id'         => 'hero',
        'kind'       => 'tag',
        'tag'        => 'pv-hero',
        'title'      => 'Hero',
        'icon'       => 'photo',
        'module'     => '/build/skylight/elements.js',
        'attributes' => [
            ['name' => 'heading', 'type' => 'string', 'description' => 'The hero heading text.'],
        ],
    ]);

    $request = Request::create('/dashboard');
    $request->setUserResolver(fn (): null => null);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['skylight']['widgets'])->toContain([
        'id'         => 'hero',
        'kind'       => 'tag',
        'tag'        => 'pv-hero',
        'title'      => 'Hero',
        'icon'       => 'photo',
        'module'     => '/build/skylight/elements.js',
        'attributes' => [
            ['name' => 'heading', 'type' => 'string', 'description' => 'The hero heading text.'],
        ],
        'defaultZone' => 'grid',
    ]);
});

it('omits a widget that was never registered, as a disabled addon would leave it', function (): void {
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn (): null => null);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect(collect($shared['skylight']['widgets'])->pluck('id'))->not->toContain('hero');
});
