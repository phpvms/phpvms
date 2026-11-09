<?php

declare(strict_types=1);

namespace PhpvmsAddonFixture\Providers;

use App\Contracts\Addons\AddonServiceProvider;
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
class AcmeServiceProvider extends AddonServiceProvider
{
    /**
     * What onBoot() found already registered by the auto-wiring.
     *
     * @var array{views: bool, translation: bool, migrations: bool}|null
     */
    public static ?array $seenInOnBoot = null;

    #[Override]
    protected function onBoot(): void
    {
        self::$seenInOnBoot = [
            'views'       => array_key_exists('acme', $this->app->make('view.finder')->getHints()),
            'translation' => __('acme::messages.hi') === 'Hello',
            'migrations'  => collect($this->app->make('migrator')->paths())->contains(fn (string $path): bool => str_ends_with($path, 'acme/database/migrations')),
        ];
    }

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
