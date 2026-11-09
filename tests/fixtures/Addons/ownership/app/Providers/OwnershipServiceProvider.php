<?php

declare(strict_types=1);

namespace PhpvmsOwnershipFixture\Providers;

use App\Contracts\Addons\AddonServiceProvider;
use Override;
use RuntimeException;

/**
 * Fixture addon provider: records its lifecycle calls.
 */
class OwnershipServiceProvider extends AddonServiceProvider
{
    /**
     * @var list<array<int, mixed>>
     */
    public static array $calls = [];

    /**
     * Name of the lifecycle method that throws, if any.
     */
    public static ?string $throwOn = null;

    /**
     * Directory whose module.json is checked when onUninstall() runs.
     */
    public static ?string $watchPath = null;

    /**
     * Records the addon root the provider resolves, which must be where the
     * files were installed to, not where they were staged.
     */
    #[Override]
    public function onInstall(): void
    {
        $this->record('onInstall', realpath($this->addonBasePath()));
    }

    #[Override]
    public function onUpdate(string $from, string $to): void
    {
        $this->record('onUpdate', $from, $to);
    }

    #[Override]
    public function onUninstall(bool $removeData): void
    {
        $this->record('onUninstall', $removeData, self::$watchPath !== null && is_file(self::$watchPath.'/module.json'));
    }

    private function record(string $method, mixed ...$arguments): void
    {
        self::$calls[] = [$method, ...$arguments];

        if (self::$throwOn === $method) {
            throw new RuntimeException($method.' failed');
        }
    }
}
