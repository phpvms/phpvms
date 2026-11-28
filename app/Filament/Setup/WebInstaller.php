<?php

declare(strict_types=1);

namespace App\Filament\Setup;

use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Actions\ResolveSiteState;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SiteState;
use App\Features\Setup\Enums\StepOutcome;
use App\Features\Setup\Support\RunSnapshot;
use App\Features\Setup\Support\SetupRunState;
use App\Filament\Setup\Concerns\ShowsSetupRun;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;
use Override;
use Throwable;

/**
 * Installs a fresh site from the browser, one step per Livewire request, so it
 * needs no cron. The page's client loop calls advance() until the run is done.
 */
class WebInstaller extends SimplePage
{
    use ShowsSetupRun {
        retry as protected retryRun;
    }

    protected static ?string $slug = 'install';

    protected string $view = 'filament.setup.web-installer';

    protected Width|string|null $maxContentWidth = Width::ThreeExtraLarge;

    // Set while the only step left is the Octane reload, which only cron can do.
    #[Locked]
    public bool $waitingForCron = false;

    public static function canAccess(): bool
    {
        return app(ResolveSiteState::class)->handle() === SiteState::Install;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (in_array($this->currentStatus(), [RunStatus::Requested, RunStatus::Running, RunStatus::Failed], true)) {
            return;
        }

        $this->requestRun();
    }

    /**
     * Runs the next step. The client loop reads the word it returns:
     * `ran` goes straight on, `wait` pauses first, `failed` stops until Retry.
     */
    public function advance(): string
    {
        $state = app(ResolveSiteState::class)->handle();

        // Cron or a demo seed may have finished the run since this page loaded.
        if (in_array($state, [SiteState::FirstUser, SiteState::Current], true)) {
            return $this->leave();
        }

        // V7Upgrade, UpdatePending and Unavailable belong to other pages and never reach this one.
        abort_unless($state === SiteState::Install, 403);

        // A cron tick held the lock when the page mounted, so nothing was recorded; ask again.
        if (!$this->currentStatus() instanceof RunStatus) {
            $this->requestRun();
        }

        $outcome = app(RunSetup::class)->runNextStep();
        $this->waitingForCron = $outcome === StepOutcome::WaitingForCron;
        unset($this->run, $this->log, $this->screen, $this->failedStep);

        if ($outcome === StepOutcome::Finished || ($outcome === StepOutcome::NothingRequested && $this->screen() === 'done')) {
            return $this->leave();
        }

        return match ($outcome) {
            StepOutcome::StepRan => 'ran',
            StepOutcome::Failed  => 'failed',
            default              => $this->screen() === 'failed' ? 'failed' : 'wait',
        };
    }

    public function retry(): void
    {
        // No auth route here, so a finished site must not be re-run from this page.
        abort_unless(app(ResolveSiteState::class)->handle() === SiteState::Install, 403);

        $this->retryRun();
    }

    #[Override]
    public function getTitle(): string|Htmlable
    {
        return __('installer.web_installer.title');
    }

    #[Override]
    public function getHeading(): string|Htmlable|null
    {
        return __('installer.web_installer.title');
    }

    /**
     * Sends the browser on by what the site has become: the first admin form, or login.
     */
    private function leave(): string
    {
        $this->redirect(
            app(ResolveSiteState::class)->handle() === SiteState::FirstUser
                ? CreateFirstUser::path()
                : Filament::getPanel('admin')->getLoginUrl()
        );

        return 'finished';
    }

    private function requestRun(): void
    {
        try {
            $fresh = app(DetectPendingWork::class)->handle()->freshInstall;
        } catch (Throwable) {
            // An unreachable database reads as a fresh one; the run's first steps then fail where the operator can see it.
            $fresh = true;
        }

        app(RunSetup::class)->request('installer', fresh: $fresh);
    }

    private function currentStatus(): ?RunStatus
    {
        try {
            $run = app(SetupRunState::class)->read();
        } catch (Throwable) {
            return null;
        }

        return $run instanceof RunSnapshot ? $run->status : null;
    }
}
