<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Support\CronHealth;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Services\CronService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Override;
use UnitEnum;

/**
 * Starts an 8.x update from the panel; progress is shown on /system/upgrade.
 */
class SystemUpdates extends Page
{
    protected static ?string $slug = 'system/updates';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected string $view = 'filament.pages.system-updates';

    public bool $confirmed = false;

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

    public function cronLine(): string
    {
        return app(CronService::class)->getCronExecString();
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
            Notification::make()->title(__('installer.updater.nothing_to_update'))->warning()->send();

            return;
        }

        // A refused request means a run is already active; its progress is there too.
        if (!app(RunSetup::class)->request((string) Filament::auth()->id(), fresh: false)) {
            Notification::make()->title(__('installer.update_in_progress'))->warning()->send();
        }

        $this->redirect('/system/upgrade');
    }
}
