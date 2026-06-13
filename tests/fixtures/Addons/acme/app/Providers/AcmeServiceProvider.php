<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Providers;

use App\Contracts\Modules\ServiceProvider;
use Override;

/**
 * Fixture addon provider — extends the base to exercise all auto-wire paths.
 *
 * Overrides addonNamespace() to return a predictable key ('acme') regardless
 * of the directory name at test time.
 *
 * addonBasePath() uses the default reflection-based resolution, which proves
 * that the reflection walk-up logic works for this file layout:
 *   {root}/app/Providers/AcmeServiceProvider.php  → dirname(..., 3) = {root}
 */
class AcmeServiceProvider extends ServiceProvider
{
    #[Override]
    protected function addonNamespace(): string
    {
        return 'acme';
    }

    #[Override]
    protected function addonRootNamespace(): string
    {
        return 'PhpvmsAddonFixture';
    }
}
