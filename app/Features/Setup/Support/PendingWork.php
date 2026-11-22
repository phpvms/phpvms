<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

/**
 * What stands between the database and the state the installed code expects.
 */
final readonly class PendingWork
{
    /**
     * @param list<string> $pendingMigrations
     * @param list<string> $pendingDataMigrations
     */
    public function __construct(
        public bool $freshInstall,
        public array $pendingMigrations,
        public array $pendingDataMigrations,
        public bool $pendingCoreSeeds,
        public bool $runUnfinished,
        public ?string $stateError = null,
    ) {}

    /**
     * Whether the admin panel must wait for a run. Addon seeds never count: an
     * addon seeder that can never finish would lock the panel out for good.
     */
    public function blocksPanel(): bool
    {
        return $this->schemaBehind() || $this->runUnfinished;
    }

    /**
     * Whether core cron events must wait. A failed run an operator brought back up no longer holds them.
     */
    public function blocksCron(bool $failedRunTakenOver): bool
    {
        return $failedRunTakenOver ? $this->schemaBehind() : $this->blocksPanel();
    }

    private function schemaBehind(): bool
    {
        return $this->freshInstall
            || $this->pendingMigrations !== []
            || $this->pendingDataMigrations !== []
            || $this->pendingCoreSeeds;
    }

    public function isCurrent(): bool
    {
        return !$this->blocksPanel();
    }
}
