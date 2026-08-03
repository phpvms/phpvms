<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\AddonRegistry;
use App\Addons\Support\BootCache;
use App\Addons\Support\RegistrySignatureVerifier;
use App\Models\Addon;
use App\Models\User;
use App\Services\CronService;
use App\Services\SettingService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Modules\AddonManager\Jobs\InstallAddonJob;
use Modules\AddonManager\Services\RegistryClient;
use Modules\AddonManager\Support\InstallProgress;

beforeEach(function (): void {
    // Autoload the addon-manager module from the real modules dir first…
    app(BootCache::class)->delete();
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonAutoLoader::class)->register(app());

    // …then redirect install targets to a scratch dir.
    $this->work = sys_get_temp_dir().'/addon-install-'.uniqid('', true);
    File::ensureDirectoryExists($this->work.'/modules');
    config([
        'addons.paths.base'          => $this->work.'/modules',
        'addons.paths.staging'       => $this->work.'/staging',
        'addon-manager.registry_url' => 'https://registry.test',
    ]);

    Cache::flush();

    $this->secret = pinRegistryKeypair();
    $this->user = User::factory()->create();
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

function pinRegistryKeypair(): string
{
    $kp = sodium_crypto_sign_keypair();
    DB::table('settings')->updateOrInsert(
        ['id' => 'registry_public_key'],
        ['key' => 'registry.public_key', 'name' => 'Registry Public Key', 'value' => base64_encode(sodium_crypto_sign_publickey($kp)), 'group' => 'general', 'type' => 'hidden'],
    );
    app(SettingService::class)->clearMemo();

    return sodium_crypto_sign_secretkey($kp);
}

/** Build a minimal installable addon zip; optionally include a failing migration. */
function buildAddonZip(string $zipPath, string $registryId, string $version, bool $failingMigration = false): string
{
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', json_encode([
        'name'        => 'Demo Addon',
        'registry_id' => $registryId,
        'version'     => $version,
        'providers'   => [],
        'database'    => ['tables' => ['demo_things']],
    ]));
    $zip->addFromString('composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\Demo\\' => '']]]));

    if ($failingMigration) {
        $zip->addFromString('database/migrations/2026_01_01_000000_boom.php', <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void { throw new \RuntimeException('boom'); }
};
PHP);
    }

    $zip->close();

    return $zipPath;
}

/**
 * Fake the mint + artifact endpoints for one version. Signs the mint body with
 * the test secret unless $wrongSecret is given (bad-signature case).
 */
function fakeRegistry(string $registryId, string $version, string $zipPath, string $secret, ?string $shaOverride = null, ?string $signWith = null): void
{
    $sha = $shaOverride ?? hash_file('sha256', $zipPath);
    $url = sprintf('https://cdn.test/%s.zip', $version);
    $body = json_encode(['name' => $registryId, 'version' => $version, 'url' => $url, 'sha256' => $sha]);
    $sig = base64_encode(sodium_crypto_sign_detached($body, $signWith ?? $secret));

    // Version-specific stub URLs: Http::fake() accumulates stubs across calls and
    // first-match wins, so a shared wildcard would replay an exhausted stream on
    // the second install. Per-version URLs keep v1 and v2 independent.
    Http::fake([
        sprintf('registry.test/v1/releases/*/%s/download', $version) => Http::response($body, 200, ['Registry-Signature' => $sig]),
        sprintf('cdn.test/%s.zip', $version)                         => Http::response(File::get($zipPath), 200),
        'registry.test/v1/packages'                                  => Http::response(['data' => []], 200),
    ]);
}

function runInstall(string $registryId, string $version, bool $migrations, int $userId): void
{
    new InstallAddonJob($registryId, $version, $migrations, $userId)->handle(
        app(RegistryClient::class),
        app(RegistrySignatureVerifier::class),
        app(AddonRegistry::class),
    );
}

it('installs a verified addon (happy path)', function (): void {
    $zip = buildAddonZip($this->work.'/demo.zip', 'acme/demo', '1.0.0');
    fakeRegistry('acme/demo', '1.0.0', $zip, $this->secret);

    runInstall('acme/demo', '1.0.0', false, $this->user->id);

    expect(Addon::where('registry_id', 'acme/demo')->exists())->toBeTrue();
    expect(InstallProgress::get('acme/demo')['status'])->toBe('done');
    expect(DB::table('notifications')->where('notifiable_id', $this->user->id)->count())->toBe(1);
});

it('rejects a bad signature and downloads nothing', function (): void {
    $zip = buildAddonZip($this->work.'/demo.zip', 'acme/demo', '1.0.0');
    $wrongKp = sodium_crypto_sign_keypair();
    fakeRegistry('acme/demo', '1.0.0', $zip, $this->secret, signWith: sodium_crypto_sign_secretkey($wrongKp));

    runInstall('acme/demo', '1.0.0', false, $this->user->id);

    expect(Addon::where('registry_id', 'acme/demo')->exists())->toBeFalse();
    expect(InstallProgress::get('acme/demo')['status'])->toBe('error');
    expect(InstallProgress::get('acme/demo')['message'])->toContain('did not match the pinned public key');
    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), 'cdn.test'));
});

it('rejects a checksum mismatch', function (): void {
    $zip = buildAddonZip($this->work.'/demo.zip', 'acme/demo', '1.0.0');
    fakeRegistry('acme/demo', '1.0.0', $zip, $this->secret, shaOverride: str_repeat('0', 64));

    runInstall('acme/demo', '1.0.0', false, $this->user->id);

    expect(Addon::where('registry_id', 'acme/demo')->exists())->toBeFalse();
    expect(InstallProgress::get('acme/demo')['status'])->toBe('error');
});

it('fails clearly and refreshes the catalog on a yanked release (404)', function (): void {
    Http::fake([
        'registry.test/v1/releases/*/download' => Http::response(['error' => 'not_found'], 404),
        'registry.test/v1/packages'            => Http::response(['data' => []], 200),
    ]);

    runInstall('acme/demo', '1.0.0', false, $this->user->id);

    expect(Addon::where('registry_id', 'acme/demo')->exists())->toBeFalse();
    expect(InstallProgress::get('acme/demo')['message'])->toContain('no longer available');
    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/v1/packages'));
});

it('rolls back a fresh install when migrations fail', function (): void {
    $zip = buildAddonZip($this->work.'/demo.zip', 'acme/demo', '1.0.0', failingMigration: true);
    fakeRegistry('acme/demo', '1.0.0', $zip, $this->secret);

    runInstall('acme/demo', '1.0.0', true, $this->user->id);

    expect(Addon::where('registry_id', 'acme/demo')->exists())->toBeFalse();
    expect(File::isDirectory($this->work.'/modules/acme-demo'))->toBeFalse();
    expect(InstallProgress::get('acme/demo')['status'])->toBe('error');
});

it('restores the previous version when an update migration fails', function (): void {
    // Install v1 cleanly.
    $v1 = buildAddonZip($this->work.'/demo-v1.zip', 'acme/demo', '1.0.0');
    fakeRegistry('acme/demo', '1.0.0', $v1, $this->secret);
    runInstall('acme/demo', '1.0.0', false, $this->user->id);
    expect(Addon::where('registry_id', 'acme/demo')->value('version'))->toBe('1.0.0');

    // Update to v2 whose migration throws.
    $v2 = buildAddonZip($this->work.'/demo-v2.zip', 'acme/demo', '2.0.0', failingMigration: true);
    fakeRegistry('acme/demo', '2.0.0', $v2, $this->secret);
    runInstall('acme/demo', '2.0.0', true, $this->user->id);

    $addon = Addon::where('registry_id', 'acme/demo')->first();
    expect($addon)->not->toBeNull();
    expect($addon->version)->toBe('1.0.0');
    expect($addon->enabled)->toBeTrue();
    expect(File::exists($this->work.'/modules/acme-demo/module.json'))->toBeTrue();
});

it('applies WithoutOverlapping per registry id', function (): void {
    $middleware = new InstallAddonJob('acme/demo', '1.0.0', false, $this->user->id)->middleware();

    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('dispatches to the queue only when a live cron will drain it', function (): void {
    Bus::fake();
    $this->mock(CronService::class, fn ($m) => $m->shouldReceive('cronProblemExists')->andReturnFalse());

    // sync driver → always inline.
    config(['queue.default' => 'sync']);
    expect(InstallAddonJob::dispatchFor('acme/demo', '1.0.0', false, $this->user->id))->toBeTrue();

    // cron-drain enabled AND cron is alive → async.
    config(['queue.default' => 'database', 'phpvms.run_queued_jobs_in_cron' => true]);
    expect(InstallAddonJob::dispatchFor('acme/demo', '1.0.0', false, $this->user->id))->toBeFalse();
    Bus::assertDispatched(InstallAddonJob::class);
});

it('falls back to sync when cron-drain is enabled but the cron is dead', function (): void {
    Bus::fake();
    $this->mock(CronService::class, fn ($m) => $m->shouldReceive('cronProblemExists')->andReturnTrue());

    config(['queue.default' => 'database', 'phpvms.run_queued_jobs_in_cron' => true]);

    expect(InstallAddonJob::dispatchFor('acme/demo', '1.0.0', false, $this->user->id))->toBeTrue();
});

it('actually installs when run synchronously (real inline execution)', function (): void {
    $zip = buildAddonZip($this->work.'/demo.zip', 'acme/demo', '1.0.0');
    fakeRegistry('acme/demo', '1.0.0', $zip, $this->secret);
    config(['queue.default' => 'sync']);

    expect(InstallAddonJob::dispatchFor('acme/demo', '1.0.0', false, $this->user->id))->toBeTrue();
    expect(Addon::where('registry_id', 'acme/demo')->exists())->toBeTrue();
});

it('updates an installed addon to a newer version', function (): void {
    $v1 = buildAddonZip($this->work.'/demo-v1.zip', 'acme/demo', '1.0.0');
    fakeRegistry('acme/demo', '1.0.0', $v1, $this->secret);
    runInstall('acme/demo', '1.0.0', false, $this->user->id);

    $v2 = buildAddonZip($this->work.'/demo-v2.zip', 'acme/demo', '2.0.0');
    fakeRegistry('acme/demo', '2.0.0', $v2, $this->secret);
    runInstall('acme/demo', '2.0.0', false, $this->user->id);

    expect(Addon::where('registry_id', 'acme/demo')->value('version'))->toBe('2.0.0');
    expect(InstallProgress::get('acme/demo')['status'])->toBe('done');
});
