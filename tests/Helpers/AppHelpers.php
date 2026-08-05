<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;

/**
 * True when a full Laravel application is booted *and* its bindings are live.
 *
 * Pest's Tia engine replays cached results without booting the framework:
 * `Testable::setUp()` returns early on a replay, so `parent::setUp()` -- the
 * call that creates the Application -- never runs. `tearDown()` guards
 * `afterEach` on `$__replay`, but only `__beginReplay()` sets that flag, and it
 * handles `ReplayType::Pass`/`Risky` only. The `Skipped` branch calls
 * `markTestSkipped()` and returns without setting it, so `afterEach` still
 * fires for a replayed skip.
 *
 * Two different broken states reach that hook:
 *
 *  1. `app()` is a bare `Illuminate\Container\Container`, so `base_path()` dies
 *     on `Container::basePath()`.
 *  2. The `Application` exists but its bindings were flushed by Laravel's own
 *     teardown, so `app('config')` hands back the Config *facade* rather than
 *     the repository and `config()` dies with "Call to undefined method
 *     Illuminate\Support\Facades\Config::get()". An `instanceof Application`
 *     check passes straight through this, which is why the binding is probed
 *     too.
 *
 * Prefer not needing this at all: capture whatever a hook needs during
 * `beforeEach` (guaranteed booted, since `setUp()` returns before invoking it)
 * and keep `afterEach` free of container access. Use this only where a hook
 * genuinely must resolve something at teardown.
 */
function appIsBooted(): bool
{
    $app = app();

    if (!$app instanceof Application) {
        return false;
    }

    if (!$app->bound('config')) {
        return false;
    }

    try {
        return $app->make('config') instanceof Repository;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Temp paths created for the current test, so cleanup needs no container.
 *
 * Pass an array to record; call with no argument to read. Deliberately a
 * function-local static rather than a property on the test case: PHPStan cannot
 * resolve `$this` inside the `pest()` hook closures in tests/Pest.php. Safe
 * under `--parallel` because each worker is its own process and runs one test
 * at a time, and `beforeEach` always overwrites the value before `afterEach`
 * reads it.
 *
 * @param  list<string>|null $paths
 * @return list<string>
 */
function phpvmsTempPaths(?array $paths = null): array
{
    static $current = [];

    if ($paths !== null) {
        $current = $paths;
    }

    return $current;
}
