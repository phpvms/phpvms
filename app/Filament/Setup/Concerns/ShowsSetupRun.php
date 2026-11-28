<?php

declare(strict_types=1);

namespace App\Filament\Setup\Concerns;

use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\PostUpgrade\ScanThemeForDeprecations;
use App\Features\Setup\Support\RunLog;
use App\Features\Setup\Support\RunSnapshot;
use App\Features\Setup\Support\SetupRunState;
use App\Services\CronService;
use Filament\Facades\Filament;
use Livewire\Attributes\Computed;

/**
 * What the shared progress partial (filament.system.updater.progress) reads from its page.
 */
trait ShowsSetupRun
{
    // Computed so $wire cannot call view helpers; cronLine exposes server paths.
    #[Computed]
    public function run(): ?RunSnapshot
    {
        return app(SetupRunState::class)->read();
    }

    /**
     * The whole run from its log file; the state file only keeps the last lines.
     */
    #[Computed]
    public function log(): string
    {
        $run = $this->run();

        if (!$run instanceof RunSnapshot) {
            return '';
        }

        $full = RunLog::for($run->id)->read();

        return $full !== '' ? $full : implode("\n", $run->logTail);
    }

    #[Computed]
    public function screen(): string
    {
        return match ($this->run()?->status) {
            RunStatus::Requested, RunStatus::Running => 'progress',
            RunStatus::Failed                        => 'failed',
            RunStatus::Done                          => 'done',
            null                                     => 'idle',
        };
    }

    #[Computed]
    public function failedStep(): ?string
    {
        foreach ($this->run()->steps ?? [] as $step => $state) {
            if ($state['status'] === StepStatus::Failed) {
                return SetupStep::tryFrom($step)?->label() ?? $step;
            }
        }

        return null;
    }

    #[Computed]
    public function cronLine(): string
    {
        return app(CronService::class)->getCronExecString();
    }

    /**
     * @return array{theme: string, found: list<string>}|null
     */
    #[Computed]
    public function themeScan(): ?array
    {
        return app(ScanThemeForDeprecations::class)->result();
    }

    #[Computed]
    public function adminUrl(): string
    {
        return Filament::getPanel('admin')->getUrl();
    }

    public function retry(): void
    {
        if ($this->run()?->status !== RunStatus::Failed) {
            return;
        }

        // The old state is stale once the retry is queued.
        app(RunSetup::class)->request($this->run()->requestedBy, fresh: $this->run()->fresh);
        unset($this->run, $this->log, $this->screen, $this->failedStep);
    }
}
