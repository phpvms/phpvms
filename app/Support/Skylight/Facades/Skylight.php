<?php

declare(strict_types=1);

namespace App\Support\Skylight\Facades;

use App\Support\Skylight\SlotRegistry;
use App\Support\Skylight\WidgetRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the skylight extension hub.
 *
 * @method static WidgetRegistry widgets()
 * @method static SlotRegistry   slots()
 * @method static array          toArray()
 *
 * @see \App\Support\Skylight\Skylight
 */
final class Skylight extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'skylight';
    }
}
