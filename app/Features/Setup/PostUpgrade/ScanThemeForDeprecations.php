<?php

declare(strict_types=1);

namespace App\Features\Setup\PostUpgrade;

use App\Features\Setup\Contracts\PostUpgradeTask;
use App\Features\Setup\Support\PostUpgradeTaskState;
use App\Features\Themes\Actions\FindDeprecatedThemeUsage;
use Closure;

/**
 * Records which 7.x shims the active theme still uses, for the upgrade's completed screen.
 *
 * @phpstan-type Data array{theme: string, found: list<string>, complete: bool}
 */
final readonly class ScanThemeForDeprecations implements PostUpgradeTask
{
    public const string KEY = 'theme_deprecations';

    public function __construct(
        private PostUpgradeTaskState $state,
        private FindDeprecatedThemeUsage $scan,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('installer.post_upgrade.tasks.'.self::KEY);
    }

    public function pending(): bool
    {
        return !($this->state->data(self::KEY)['complete'] ?? false);
    }

    public function run(?Closure $progress = null): void
    {
        $theme = (string) setting('general.theme', config('themes.default'));

        $this->state->putData(self::KEY, ['theme' => $theme, 'found' => ($this->scan)($theme), 'complete' => true]);

        if ($progress instanceof Closure) {
            $progress(1, 1);
        }
    }

    public function progress(): array
    {
        return ['done' => $this->pending() ? 0 : 1, 'total' => 1];
    }

    /**
     * @return array{theme: string, found: list<string>}|null null until the scan has run
     */
    public function result(): ?array
    {
        $data = $this->state->data(self::KEY);

        if (!($data['complete'] ?? false)) {
            return null;
        }

        /** @var list<string> $found */
        $found = $data['found'] ?? [];

        return ['theme' => (string) ($data['theme'] ?? ''), 'found' => $found];
    }
}
