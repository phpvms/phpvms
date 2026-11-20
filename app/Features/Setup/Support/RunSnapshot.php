<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\StepStatus;

final readonly class RunSnapshot
{
    /**
     * @param array<string, array{status: StepStatus, startedAt: ?string, finishedAt: ?string}> $steps    keyed by SetupStep value
     * @param array{done: int, total: int, item: ?string}|null                                  $progress
     * @param list<string>                                                                      $logTail
     */
    public function __construct(
        public string $id,
        public RunStatus $status,
        public string $requestedBy,
        public string $requestedAt,
        public ?string $startedAt,
        public ?string $finishedAt,
        public bool $fresh,
        public array $steps,
        public ?array $progress,
        public ?string $error,
        public array $logTail,
    ) {}
}
