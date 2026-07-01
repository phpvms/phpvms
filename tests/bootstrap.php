<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// Tests must never run against a cached route table. A stray
// bootstrap/cache/routes-*.php (left behind by a prior `route:cache` or
// `optimize`, common after deploy/Docker work) makes Laravel's
// `loadRoutesFrom()` a no-op, so routes registered at runtime — notably addon
// service providers — silently fail to register and route tests break in
// confusing, order-dependent ways. Remove it before the framework boots.
foreach (glob(__DIR__.'/../bootstrap/cache/routes-*.php') ?: [] as $cachedRoutes) {
    @unlink($cachedRoutes);
}
