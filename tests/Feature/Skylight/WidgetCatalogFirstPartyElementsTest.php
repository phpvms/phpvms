<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

it('shares the first-party custom elements as tag-kind catalog entries', function (): void {
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn (): null => null);

    $shared = app(HandleInertiaRequests::class)->share($request);

    $tags = collect($shared['skylight']['widgets'])->where('kind', 'tag')->keyBy('tag');

    expect($tags->keys()->all())->toContain('pv-hero', 'pv-callout', 'pv-stat-group')
        ->and($tags['pv-hero']['title'])->toBe('Hero')
        ->and(collect($tags['pv-hero']['attributes'])->pluck('name')->all())->toBe(['heading', 'eyebrow'])
        ->and(collect($tags['pv-callout']['attributes'])->pluck('name')->all())->toBe(['variant'])
        ->and(collect($tags['pv-stat-group']['attributes'])->pluck('name')->all())->toBe(['items'])
        ->and($tags['pv-hero']['module'])->toBe('/build/skylight/elements.js');
});
