<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

it('shares the skylight widgets prop as an empty list', function (): void {
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn (): null => null);

    $shared = app(HandleInertiaRequests::class)->share($request);
    $skylight = value($shared['skylight']);

    expect($skylight['widgets'])->toBe([]);
});
