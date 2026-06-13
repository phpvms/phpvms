<?php

declare(strict_types=1);

namespace App\Addons\Support;

use Closure;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Gracefully reloads Octane workers after an addon lifecycle mutation so the
 * new boot-cache state (PSR-4 + providers) takes effect. No-op outside Octane.
 *
 * The detection and process-run are injected so the behaviour is unit-testable
 * without spawning a real process.
 */
class OctaneReloader
{
    /** @var Closure(): bool */
    private readonly Closure $underOctane;

    /** @var Closure(array<int, string>): void */
    private readonly Closure $runner;

    /**
     * @param (Closure(): bool)|null                  $underOctane Defaults to runtime detection.
     * @param (Closure(array<int,string>): void)|null $runner      Defaults to a real Process run.
     */
    public function __construct(?Closure $underOctane = null, ?Closure $runner = null)
    {
        // Octane sets $_SERVER['LARAVEL_OCTANE'] inside its worker processes; it
        // is absent under php-fpm/CLI. This is far more reliable than the
        // octane.enabled config flag (which defaults to true everywhere).
        $this->underOctane = $underOctane ?? static fn (): bool => ($_SERVER['LARAVEL_OCTANE'] ?? false) !== false;
        $this->runner = $runner ?? static function (array $command): void {
            new Process($command, base_path())->setTimeout(60)->run();
        };
    }

    /**
     * Reload Octane workers when running under Octane; otherwise no-op.
     * Best-effort: failures are logged, never thrown.
     */
    public function reload(): void
    {
        if (!($this->underOctane)()) {
            return;
        }

        try {
            ($this->runner)($this->command());
        } catch (Throwable $throwable) {
            Log::warning('Addon Octane reload failed: '.$throwable->getMessage());
        }
    }

    /**
     * @return array<int, string>
     */
    private function command(): array
    {
        $php = new PhpExecutableFinder()->find(false) ?: 'php';
        $php = str_replace('-fpm', '', $php);

        return [$php, base_path('artisan'), 'octane:reload'];
    }
}
