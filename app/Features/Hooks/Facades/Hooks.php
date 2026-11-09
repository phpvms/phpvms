<?php

declare(strict_types=1);

namespace App\Features\Hooks\Facades;

use App\Features\Hooks\FilterHook;
use App\Features\Hooks\GateHook;
use App\Features\Hooks\HookDispatcher;
use App\Features\Hooks\Testing\HookFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void  listen(string $hook, string $listener, ?string $addon = null)
 * @method static array listenersFor(string $hook)
 * @method static mixed filter(FilterHook $hook)
 * @method static void  gate(GateHook $hook)
 *
 * @see HookDispatcher
 */
class Hooks extends Facade
{
    /**
     * Replace the dispatcher with a recording fake for the current test.
     */
    public static function fake(): HookFake
    {
        static::swap($fake = new HookFake(static::getFacadeApplication()));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return HookDispatcher::class;
    }
}
