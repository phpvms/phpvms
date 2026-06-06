<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Addons\AddonRuntimeService;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'phpvms:addons-prime', description: 'Prime the addon registry and rebuild the boot cache')]
#[Signature('phpvms:addons-prime
                    {--force : Re-prime even if the boot cache already exists}')]
class AddonsPrime extends Command
{
    /**
     * Prime (or re-prime) the addon registry and write the enabled-only boot cache.
     *
     * Without --force: calls primeIfNeeded() — no-ops when the boot cache already exists.
     * With    --force: calls run() — always rebuilds the cache and reconciles disk→DB.
     *
     * Returns self::SUCCESS on completion; self::FAILURE on exception.
     */
    public function handle(AddonRuntimeService $prime): int
    {
        try {
            if ($this->option('force')) {
                $this->components->task('Priming addon registry (forced)', static function () use ($prime): void {
                    $prime->run();
                });
            } else {
                $primed = false;

                $this->components->task('Priming addon registry', static function () use ($prime, &$primed): void {
                    $primed = $prime->primeIfNeeded();
                });

                if (!$primed) {
                    $this->components->info('Boot cache present — nothing to prime (use --force to rebuild).');
                }
            }
        } catch (Throwable $throwable) {
            $this->components->error('Addon prime failed: '.$throwable->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
