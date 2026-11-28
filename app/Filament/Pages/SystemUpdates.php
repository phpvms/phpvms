<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Support\CachedPendingWork;
use App\Features\Setup\Support\CronHealth;
use App\Features\Setup\Support\RunSnapshot;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\Setup\Concerns\ShowsSetupRun;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Override;
use UnitEnum;

/**
 * Starts an 8.x update from the panel and follows its progress. The cron task does
the work; this page only requests a run and reads the state file.
 */
class SystemUpdates extends Page
{
    use ShowsSetupRun;

    protected static ?string $slug = 'system/update';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected string $view = 'filament.pages.system-updates';

    public bool $confirmed = false;

    // The done screen shows only for a run this page watched, not for an old Done run on disk.
    #[Locked]
    public ?string $watchedRunId = null;

    public function mount(): void
    {
        $this->watch();
    }

    public function hydrate(): void
    {
        $this->watch();
    }

    #[Computed]
    public function screen(): string
    {
        $run = $this->run();

        return match (true) {
            $run?->status === RunStatus::Requested, $run?->status === RunStatus::Running => 'progress',
            $run?->status === RunStatus::Failed                                          => 'failed',
            $run?->status === RunStatus::Done && $run->id === $this->watchedRunId        => 'done',
            default                                                                      => 'idle',
        };
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('access_admin') ?? false;
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('installer.updater.system_updates');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('installer.updater.system_updates');
    }

    public function pending(): bool
    {
        return app(DetectPendingWork::class)->handle()->blocksPanel();
    }

    public function cronFresh(): bool
    {
        return app(CronHealth::class)->isFresh();
    }

    public function start(): void
    {
        // Before the 7.0 upgrade completes, /system/upgrade owns the run.
        if (!app(UpgradeCompleted::class)->exists() || !$this->confirmed || !app(CronHealth::class)->isFresh()) {
            return;
        }

        $pending = app(DetectPendingWork::class)->handle();

        if (!$pending->blocksPanel() || $pending->freshInstall) {
            // The panel's cached verdict may be the stale one that sent the admin here.
            app(CachedPendingWork::class)->forget();

            Notification::make()->title(__('installer.updater.nothing_to_update'))->warning()->send();

            return;
        }

        // A refused request means a run is already active; its progress shows here too.
        if (!app(RunSetup::class)->request((string) Filament::auth()->id(), fresh: false)) {
            Notification::make()->title(__('installer.update_in_progress'))->warning()->send();
        }

        unset($this->run, $this->log, $this->screen, $this->failedStep);
        $this->watch();
    }

    private function watch(): void
    {
        $run = $this->run();

        if ($run instanceof RunSnapshot && $run->status !== RunStatus::Done) {
            $this->watchedRunId = $run->id;
        }
    }
}
