<?php

declare(strict_types=1);

namespace App\Support\Metrics\Facades;

use App\Support\Metrics\MetricRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the page-metrics hub.
 *
 * @method static array          all()
 * @method static MetricRegistry registry()
 * @method static bool           has(string $id)
 * @method static mixed          resolve(string $id, array $parameters = [])
 * @method static string|null    format(string $id, mixed $value)
 * @method static int            bumpNamespace()
 *
 * @see \App\Support\Metrics\Metrics
 */
final class Metrics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'metrics';
    }
}
