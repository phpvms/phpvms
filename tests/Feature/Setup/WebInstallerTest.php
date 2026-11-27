<?php

declare(strict_types=1);

use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Actions\ResolveSiteState;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\SiteState;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\Support\CachedPendingWork;
use App\Features\Setup\Support\CoreSeeds;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\Setup\CreateFirstUser;
use App\Filament\Setup\WebInstaller;
use App\Http\Middleware\EnsureDatabaseCurrent;
use App\Models\User;
use App\Services\CronService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\ParallelTesting;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/phpvms-web-installer-'.uniqid();
    mkdir($this->dir);
    $this->completed = new UpgradeCompleted($this->dir.'/completed', databaseSignal: false);
    $this->ran = new ArrayObject();
    app()->instance(UpgradeCompleted::class, $this->completed);
    DB::table('users')->delete();
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

function installerFakeStep(SetupStep $which, ArrayObject $ran, ?Closure $throws = null): Step
{
    return new readonly class($which, $ran, $throws) implements Step
    {
        public function __construct(private SetupStep $which, private ArrayObject $ran, private ?Closure $throws) {}

        public function step(): SetupStep
        {
            return $this->which;
        }

        public function applies(StepContext $ctx): bool
        {
            return true;
        }

        public function run(StepContext $ctx): void
        {
            $this->ran->append($this->which);

            if ($this->throws instanceof Closure && ($this->throws)()) {
                throw new RuntimeException('boom');
            }
        }
    };
}

/** @param list<Step> $steps */
function installerEngine(object $test, array $steps, ?Closure $webUnderOctane = null): RunSetup
{
    $engine = new RunSetup(app(SetupRunState::class), app(SetupLock::class), $steps, $test->completed, $webUnderOctane);
    app()->instance(RunSetup::class, $engine);

    return $engine;
}

/** The test database has data migrations the suite never ran; this makes the site count as current. */
function installerCurrentSite(object $test): void
{
    $paths = new class() extends MigrationPaths
    {
        public function pendingSchema(): array
        {
            return [];
        }

        public function pendingData(): array
        {
            return [];
        }
    };
    $detect = new DetectPendingWork($paths, app(CoreSeeds::class), app(SetupRunState::class));
    app()->instance(CachedPendingWork::class, new CachedPendingWork($detect, $paths, app(CoreSeeds::class), app(SetupRunState::class), $test->dir.'/pending.json'));
}

function installerPage(Testable $component): WebInstaller
{
    $page = $component->instance();
    assert($page instanceof WebInstaller);

    return $page;
}

function installerDrain(Testable $component, int $max = 40): Testable
{
    for ($i = 0; $i < $max && app(SetupRunState::class)->read()?->status !== RunStatus::Done; $i++) {
        $component->call('advance');

        if (app(SetupRunState::class)->read()?->status === RunStatus::Failed) {
            break;
        }
    }

    return $component;
}

test('runs every step across requests and lands on the first-user page', function (): void {
    installerEngine($this, [
        installerFakeStep(SetupStep::Preflight, $this->ran),
        installerFakeStep(SetupStep::Migrate, $this->ran),
        installerFakeStep(SetupStep::Seed, $this->ran),
    ]);

    $component = Livewire::test(WebInstaller::class);

    // Mounting only queues the run; nothing has run yet.
    expect(app(SetupRunState::class)->read()?->requestedBy)->toBe('installer')
        ->and($this->ran->getArrayCopy())->toBe([]);

    $component->call('advance')->assertReturned('ran');

    expect($this->ran->getArrayCopy())->toBe([SetupStep::Preflight]);

    installerDrain($component)->assertRedirect(CreateFirstUser::path());

    expect($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::Migrate, SetupStep::Seed])
        ->and(app(SetupRunState::class)->read()?->status)->toBe(RunStatus::Done)
        ->and($this->completed->exists())->toBeTrue();
});

test('a reload in the middle of the run resumes it without starting over', function (): void {
    installerEngine($this, [
        installerFakeStep(SetupStep::Preflight, $this->ran),
        installerFakeStep(SetupStep::Migrate, $this->ran),
        installerFakeStep(SetupStep::Seed, $this->ran),
    ]);

    $first = Livewire::test(WebInstaller::class)->call('advance')->call('advance');
    $runId = app(SetupRunState::class)->read()?->id;

    expect($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::Migrate]);

    $reloaded = Livewire::test(WebInstaller::class);

    expect(app(SetupRunState::class)->read()?->id)->toBe($runId)
        ->and(installerPage($reloaded)->screen())->toBe('progress');

    installerDrain($reloaded)->assertRedirect(CreateFirstUser::path());

    expect($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::Migrate, SetupStep::Seed]);
});

test('retry after a failed step keeps the done steps', function (): void {
    $broken = true;
    installerEngine($this, [
        installerFakeStep(SetupStep::Preflight, $this->ran),
        installerFakeStep(SetupStep::LinkAddons, $this->ran),
        installerFakeStep(SetupStep::Migrate, $this->ran, function () use (&$broken): bool {
            return $broken;
        }),
    ]);

    $component = installerDrain(Livewire::test(WebInstaller::class));

    expect(installerPage($component)->screen())->toBe('failed')
        ->and(installerPage($component)->failedStep())->toBe(SetupStep::Migrate->label())
        ->and(app(SetupRunState::class)->read()?->steps['link_addons']['status'])->toBe(StepStatus::Done);

    $broken = false;
    $this->ran->exchangeArray([]);

    $component->call('retry');

    expect(installerPage($component)->screen())->toBe('progress');

    installerDrain($component);
    $component->assertRedirect(CreateFirstUser::path());

    expect($this->ran->getArrayCopy())->toBe([SetupStep::Preflight, SetupStep::Migrate]);
});

test('under octane it says cron is needed and the run finishes on the next cron tick', function (): void {
    $engine = installerEngine($this, [
        installerFakeStep(SetupStep::Migrate, $this->ran),
        installerFakeStep(SetupStep::OctaneReload, $this->ran),
    ], fn (): bool => true);

    $component = Livewire::test(WebInstaller::class)->call('advance')->assertReturned('ran');

    expect(installerPage($component)->waitingForCron)->toBeFalse();

    $component->call('advance')->assertReturned('wait')->assertSet('waitingForCron', true);

    expect(app(SetupRunState::class)->read()?->status)->toBe(RunStatus::Running)
        ->and(installerPage($component)->cronLine())->toBe(app(CronService::class)->getCronExecString());

    // The cron tick runs the console path, which can reload Octane.
    $engine->handle();

    $component->call('advance')->assertRedirect(CreateFirstUser::path());
});

test('follows a run that cron already took', function (): void {
    $engine = installerEngine($this, [installerFakeStep(SetupStep::Migrate, $this->ran)]);
    $component = Livewire::test(WebInstaller::class);

    $other = new SetupLock(storage_path('framework/testing/phpvms-update-'.(ParallelTesting::token() ?: '0').'.lock'));
    $other->acquire();

    $component->call('advance')->assertReturned('wait');

    $other->release();
    $engine->handle();

    $component->call('advance')->assertRedirect(CreateFirstUser::path());
    expect($this->ran->getArrayCopy())->toBe([SetupStep::Migrate]);
});

test('the installer is closed once the site has users', function (): void {
    User::factory()->create();
    $this->completed->write();

    Livewire::test(WebInstaller::class)->assertStatus(403);
});

test('a replayed step request runs nothing once a user exists and sends the browser to login', function (): void {
    installerEngine($this, [installerFakeStep(SetupStep::Migrate, $this->ran)]);

    // The page was loaded before the first user was created.
    $component = Livewire::test(WebInstaller::class);

    User::factory()->create();
    $this->completed->write();
    app(SetupRunState::class)->finished(RunStatus::Done);
    installerCurrentSite($this);

    $component->call('advance')->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

    expect($this->ran->getArrayCopy())->toBe([]);
});

test('a step request on a v7 site is refused', function (): void {
    installerEngine($this, [installerFakeStep(SetupStep::Migrate, $this->ran)]);
    $component = Livewire::test(WebInstaller::class);

    User::factory()->create();

    $component->call('advance')->assertForbidden();

    expect($this->ran->getArrayCopy())->toBe([]);
});

test('retry is refused once the site has users', function (): void {
    installerEngine($this, [installerFakeStep(SetupStep::Migrate, $this->ran, fn (): bool => true)]);
    $component = installerDrain(Livewire::test(WebInstaller::class));

    expect(installerPage($component)->screen())->toBe('failed');

    User::factory()->create();
    $this->completed->write();
    $this->ran->exchangeArray([]);

    $component->call('retry')->assertForbidden();

    expect($this->ran->getArrayCopy())->toBe([])
        ->and(app(SetupRunState::class)->read()?->status)->toBe(RunStatus::Failed);
});

test('a seed that creates a user ends the run without offering the first-user form', function (): void {
    $seed = new readonly class($this->ran) implements Step
    {
        public function __construct(private ArrayObject $ran) {}

        public function step(): SetupStep
        {
            return SetupStep::Seed;
        }

        public function applies(StepContext $ctx): bool
        {
            return true;
        }

        public function run(StepContext $ctx): void
        {
            $this->ran->append(SetupStep::Seed);
            User::factory()->create();
        }
    };
    // The test database already has its migrations, so queue the run as the fresh install it stands for.
    installerEngine($this, [$seed])->request('installer', fresh: true);

    installerDrain(Livewire::test(WebInstaller::class))->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

    expect($this->ran->getArrayCopy())->toBe([SetupStep::Seed])
        ->and(app(SetupRunState::class)->read()?->status)->toBe(RunStatus::Done)
        ->and(app(ResolveSiteState::class)->handle())->not->toBe(SiteState::FirstUser)
        ->and(CreateFirstUser::canAccess())->toBeFalse();
});

test('a replayed installer snapshot is sent to the upgrader once the site has users', function (): void {
    $this->withMiddleware(EnsureDatabaseCurrent::class);

    $page = $this->get('/admin/install')->assertOk();
    preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $match);
    $component = ['snapshot' => htmlspecialchars_decode($match[1], ENT_QUOTES), 'updates' => [], 'calls' => []];

    User::factory()->create();
    Livewire::flushState();

    $this->postJson(EndpointResolver::updatePath(), ['components' => [$component]], ['X-Livewire' => 'true'])
        ->assertRedirect('/system/upgrade');
});

test('a page opened while cron holds the lock records its run once the lock is free', function (): void {
    installerEngine($this, [installerFakeStep(SetupStep::Migrate, $this->ran)]);

    $other = new SetupLock(storage_path('framework/testing/phpvms-update-'.(ParallelTesting::token() ?: '0').'.lock'));
    $other->acquire();

    $component = Livewire::test(WebInstaller::class);

    expect(app(SetupRunState::class)->read())->toBeNull();

    $component->call('advance')->assertReturned('wait');

    $other->release();

    $component->call('advance')->assertReturned('ran');

    expect($this->ran->getArrayCopy())->toBe([SetupStep::Migrate])
        ->and(app(SetupRunState::class)->read()?->requestedBy)->toBe('installer');
});
