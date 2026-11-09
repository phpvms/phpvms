<?php

declare(strict_types=1);

namespace PhpvmsBadBaseFixture\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Fixture: an addon provider on Laravel's own base, which core must refuse.
 */
class LaravelBaseProvider extends ServiceProvider {}
