<?php

declare(strict_types=1);

namespace PhpvmsOwnershipFixture\Providers;

use App\Contracts\Addons\AddonServiceProvider;
use Override;
use PhpvmsOwnershipFixture\Models\OwnWidget;
use RuntimeException;

/**
 * Fixture addon provider: declares one model and records its lifecycle calls.
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

    #[Override]
    public function registerModels(): array
    {
        return [OwnWidget::class];
    }

    #[Override]
    public function onInstall(): void
    {
        $this->record('onInstall');
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
