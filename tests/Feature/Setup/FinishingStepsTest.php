<?php

declare(strict_types=1);

use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Steps\Optimize;
use App\Features\Setup\Steps\PassportKeys;
use App\Features\Setup\Steps\ReloadOctane;
use App\Features\Setup\Steps\WriteMarkers;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\StepContext;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Models\User;
use App\Support\InstallMarker;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;
use Laravel\Passport\Passport;

function finishingContext(): StepContext
{
    return new StepContext(false, function (string $line): void {}, function (int $done, int $total, ?string $label): void {});
}

function fakeArtisan(Closure $onCall, array $commands = []): object
{
    return new readonly class($onCall, $commands)
    {
        public function __construct(private Closure $onCall, private array $commands) {}

        public function all(): array
        {
            return $this->commands;
        }

        public function call(string $command, array $parameters = []): int
        {
            return ($this->onCall)($command);
        }

        public function output(): string
        {
            return '';
        }
    };
}

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/phpvms-finishing-'.uniqid();
    mkdir($this->tmp);
    $this->bootstrapPath = app()->bootstrapPath();
    $this->keyPath = Passport::keyPath('oauth-public.key');
});

afterEach(function (): void {
    app()->useBootstrapPath($this->bootstrapPath);
    Passport::loadKeysFrom(dirname($this->keyPath));
    Artisan::clearResolvedInstances();

    foreach (glob($this->tmp.'/{,cache/}*', GLOB_BRACE) ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    @rmdir($this->tmp.'/cache');
    @rmdir($this->tmp);
});

test('marker step writes no install marker when there is no user', function (): void {
    $marker = resolve(InstallMarker::class);
    $marker->remove();

    new WriteMarkers()->run(finishingContext());

    expect($marker->exists())->toBeFalse();
});

test('marker step writes the install marker when a user exists', function (): void {
    $marker = resolve(InstallMarker::class);
    $marker->remove();
    User::factory()->create();

    new WriteMarkers()->run(finishingContext());

    expect($marker->exists())->toBeTrue();
});

test('passport keys are left alone when they exist', function (): void {
    config(['passport.private_key' => null, 'passport.public_key' => null]);
    Passport::loadKeysFrom($this->tmp);
    file_put_contents($this->tmp.'/oauth-public.key', 'public');
    file_put_contents($this->tmp.'/oauth-private.key', 'private');

    new PassportKeys()->run(finishingContext());

    expect(file_get_contents($this->tmp.'/oauth-public.key'))->toBe('public')
        ->and(file_get_contents($this->tmp.'/oauth-private.key'))->toBe('private');
});

test('optimize fails when the config cache is not written', function (): void {
    Artisan::swap(fakeArtisan(fn (string $command): int => 0));

    expect(fn () => new Optimize()->run(finishingContext()))
        ->toThrow(RuntimeException::class, 'config cache');
});

test('facades resolve against the same app after the optimize step', function (): void {
    $app = app();
    mkdir($this->tmp.'/cache');
    $previous = [$_SERVER['APP_CONFIG_CACHE'] ?? null, $_ENV['APP_CONFIG_CACHE'] ?? null];
    $_SERVER['APP_CONFIG_CACHE'] = $_ENV['APP_CONFIG_CACHE'] = $this->tmp.'/cache/config.php';

    Artisan::swap(fakeArtisan(function (string $command) use ($app): int {
        if ($command === 'config:cache') {
            file_put_contents($app->getCachedConfigPath(), '<?php return [];');

            // What bootstrap/app.php does inside config:cache.
            $other = new Application(sys_get_temp_dir());
            Container::setInstance($other);
            Facade::setFacadeApplication($other);
        }

        return 0;
    }));

    try {
        new Optimize()->run(finishingContext());
    } finally {
        [$_SERVER['APP_CONFIG_CACHE'], $_ENV['APP_CONFIG_CACHE']] = $previous;
    }

    expect(Container::getInstance())->toBe($app)
        ->and(Facade::getFacadeApplication())->toBe($app);
});

test('the completion file is written only after the run ends done', function (): void {
    $state = new SetupRunState($this->tmp.'/state.json');
    $completed = new UpgradeCompleted($this->tmp.'/completed');
    $state->request('cli', false);

    $failing = new class() implements Step
    {
        public function step(): SetupStep
        {
            return SetupStep::MaintenanceUp;
        }

        public function applies(StepContext $ctx): bool
        {
            return true;
        }

        public function run(StepContext $ctx): void
        {
            throw new RuntimeException('site still down');
        }
    };

    new RunSetup($state, new SetupLock($this->tmp.'/run.lock'), [$failing], $completed)->handle();

    expect(file_exists($this->tmp.'/completed'))->toBeFalse();
});

test('octane reload is skipped when the octane commands are not registered', function (): void {
    $called = [];
    Artisan::swap(fakeArtisan(function (string $command) use (&$called): int {
        $called[] = $command;

        return 0;
    }));

    new ReloadOctane()->run(finishingContext());

    expect($called)->toBe([]);
});
