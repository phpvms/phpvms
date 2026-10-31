<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

/**
 * 5.5: nothing routes to the token showcase.
 */
beforeEach(function (): void {
    useAirside();
    $this->withoutVite();
});

it('has no public route to the showcase', function (): void {
    $uris = collect(app('router')->getRoutes()->getRoutes())->map->uri();

    expect($uris->filter(fn (string $uri): bool => str_contains($uri, 'showcase')))->toBeEmpty();
});
