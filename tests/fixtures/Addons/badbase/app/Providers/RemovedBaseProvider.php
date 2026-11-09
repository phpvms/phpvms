<?php

declare(strict_types=1);

namespace PhpvmsBadBaseFixture\Providers;

use App\Contracts\Modules\ServiceProvider;

/**
 * Fixture: an addon provider on the removed base class. Loading it fails.
 */
class RemovedBaseProvider extends ServiceProvider {}
