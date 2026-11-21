<?php

declare(strict_types=1);

namespace App\Filament\System;

use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\PostUpgrade\ScanThemeForDeprecations;
use App\Features\Setup\Support\CronHealth;
use App\Features\Setup\Support\RunLog;
use App\Features\Setup\Support\RunSnapshot;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Services\CronService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Override;

/**
 * Starts the 7.0 -> 8.0 update and follows its progress. The cron task does
 * the work; this page only requests a run and reads the state file.
 */
class Updater extends Page
{
    protected static ?string $slug = 'upgrade';

    protected string $view = 'filament.system.updater';

    public bool $confirmed = false;

    // A page that watched a run may show its done screen after the completion file appears.
    #[Locked]
    public ?string $watchedRunId = null;

    public function mount(): void
    {
        $this->abortUnlessOpen();
    }

    // Polls and later actions must hit the guard too, not just the first load.
    public function hydrate(): void
    {
        $this->abortUnlessOpen();
    }

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
            null                                     => 'warning',
        };
    }

    #[Computed]
    public function canStart(): bool
    {
        return $this->screen() === 'warning'
            && !app(UpgradeCompleted::class)->exists()
            && app(CronHealth::class)->isFresh();
    }

    #[Computed]
    public function cronLine(): string
    {
        return app(CronService::class)->getCronExecString();
    }

    #[Computed]
    public function cronFresh(): bool
    {
        return app(CronHealth::class)->isFresh();
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

    public function start(): void
    {
        if (app(UpgradeCompleted::class)->exists() || !$this->confirmed || !app(CronHealth::class)->isFresh()) {
            return;
        }

        $pending = app(DetectPendingWork::class)->handle();

        if (!$pending->blocksPanel() || $pending->freshInstall) {
            Notification::make()->title(__('installer.updater.nothing_to_update'))->warning()->send();

            return;
        }

        $this->request();
    }

    public function retry(): void
    {
        if ($this->run()?->status !== RunStatus::Failed) {
            return;
        }

        $this->request();
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make(__('installer.updater.steps.backup'))
                    ->schema([ViewComponent::make('filament.system.updater.backup')]),
                Step::make(__('installer.updater.steps.upgrade'))
                    ->schema([ViewComponent::make('filament.system.updater.progress')]),
            ])
                // The wizard keeps its step in Alpine; a per-screen key makes Livewire
                // replace it, so startOnStep applies again when the run moves on.
                ->key(fn (): string => 'updater-wizard-'.$this->screen())
                ->startOnStep(fn (): int => $this->screen() === 'warning' ? 1 : 2)
                ->nextAction(fn (Action $action): Action => $action->hidden())
                ->previousAction(fn (Action $action): Action => $action->hidden())
                ->extraAttributes(['class' => 'updater-wizard']),
        ]);
    }

    #[Override]
    public function getTitle(): string
    {
        return __('installer.upgrade_phpvms');
    }

    private function request(): void
    {
        // The web page only queues the run; cron picks it up.
        if (!app(RunSetup::class)->request('web', fresh: false, log: '==> '.__('installer.updater.waiting_for_cron'))) {
            Notification::make()->title(__('installer.update_in_progress'))->warning()->send();
        }
    }

    /**
     * Open before the update completes, and while a run is still live or failed
     * so 8.x runs and Retry keep working with the site down.
     */
    private function abortUnlessOpen(): void
    {
        $run = $this->run();

        if ($run instanceof RunSnapshot && $run->status !== RunStatus::Done) {
            $this->watchedRunId = $run->id;
        }

        if (!app(UpgradeCompleted::class)->exists()) {
            return;
        }

        $open = in_array($run?->status, [RunStatus::Requested, RunStatus::Running, RunStatus::Failed], true)
            || ($run?->status === RunStatus::Done && $run->id === $this->watchedRunId);

        abort_unless($open, 404);
    }
}
