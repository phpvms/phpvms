<?php

declare(strict_types=1);

namespace App\Support\Extensions\Facades;

use App\Support\Extensions\SlotRegistry;
use App\Support\Extensions\WidgetRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the extension hub.
 *
 * @method static WidgetRegistry widgets()
 * @method static SlotRegistry   slots()
 * @method static array          toArray()
 *
 * @see \App\Support\Extensions\Extensions
 */
final class Extensions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'extensions';
    }
}
