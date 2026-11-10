<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Addons\Sources\ZipSource;
use App\Addons\Support\CoreTables;
use App\Models\Addon;
use App\Services\Installer\MigrationService;
use Composer\Autoload\ClassLoader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpvmsOwnershipFixture\Providers\OwnershipServiceProvider;

/**
 * Uninstall against an addon that really lives in a writable addon root, so its
 * files are deleted. AddonUninstallTest cannot catch an ordering bug here: its
 * fixture sits outside the writable roots, so its files are never removed.
 */
beforeEach(function (): void {
    Addon::query()->delete();

    $this->root = sys_get_temp_dir().'/asp-ownership-'.uniqid('', true);
    Config::set('addons.paths.installed', $this->root);
    Config::set('addons.paths.themes', $this->root.'-themes');
    File::ensureDirectoryExists($this->root);

    $this->fixture = realpath(__DIR__.'/../../fixtures/Addons/ownership');

    foreach (spl_autoload_functions() as $entry) {
        if (is_array($entry) && $entry[0] instanceof ClassLoader) {
            $entry[0]->addPsr4('PhpvmsOwnershipFixture\\', $this->fixture.'/app');

            break;
        }
    }

    OwnershipServiceProvider::$calls = [];
    OwnershipServiceProvider::$throwOn = null;

    // Copies the fixture into the writable root, migrates it and gives it a row.
    $this->installFixture = function (array $manifest = []): string {
        $dir = $this->root.'/acme-ownership';
        File::copyDirectory($this->fixture, $dir);

        if ($manifest !== []) {
            File::put($dir.'/module.json', json_encode([...json_decode((string) file_get_contents($dir.'/module.json'), true), ...$manifest]));
        }

        Artisan::call('migrate', ['--force' => true, '--realpath' => true, '--path' => [$dir.'/database/migrations']]);

        Addon::factory()->create([
            'name'        => 'Ownership',
            'registry_id' => 'acme/ownership',
            'namespace'   => 'PhpvmsOwnershipFixture',
            'version'     => '1.0.0',
            'path'        => $dir,
            'enabled'     => false,
        ]);

        return $dir;
    };
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
    Schema::dropIfExists('own_widgets');
    Schema::dropIfExists('own_widget_tags');
    Schema::dropIfExists('own_plain_widgets');
});

it('drops the declared model table and the manifest tables, then deletes the files', function (): void {
    $dir = ($this->installFixture)();
    storeAddonSettingRow('acme/ownership', 'token', 'secret');

    app(AddonRegistry::class)->delete('Ownership', true);

    expect(Schema::hasTable('own_widgets'))->toBeFalse()
        ->and(Schema::hasTable('own_widget_tags'))->toBeFalse()
        ->and(is_dir($dir))->toBeFalse()
        ->and(File::directories($this->root))->toBe([])
        ->and(addonSettingRowsExist('acme/ownership'))->toBeFalse();
});

it('keeps the tables and the settings when the data is not removed', function (): void {
    $dir = ($this->installFixture)();
    storeAddonSettingRow('acme/ownership', 'token', 'secret');

    app(AddonRegistry::class)->delete('Ownership', false);

    expect(Schema::hasTable('own_widgets'))->toBeTrue()
        ->and(Schema::hasTable('own_widget_tags'))->toBeTrue()
        ->and(is_dir($dir))->toBeFalse()
        ->and(addonSettingRowsExist('acme/ownership'))->toBeTrue();
});

it('rolls the migrations back when the addon declares no tables and no models', function (): void {
    $dir = ($this->installFixture)(['providers' => [], 'database' => ['tables' => []]]);

    app(AddonRegistry::class)->delete('Ownership', true);

    expect(Schema::hasTable('own_widgets'))->toBeFalse()
        ->and(is_dir($dir))->toBeFalse();
});

it('puts the files back and keeps the addon when dropping the tables fails', function (): void {
    $dir = ($this->installFixture)();
    $this->mock(MigrationService::class)
        ->shouldReceive('dropAddonTables')->andThrow(new RuntimeException('drop failed'));

    expect(fn () => app(AddonRegistry::class)->delete('Ownership', true))->toThrow(RuntimeException::class, 'drop failed');

    expect(is_file($dir.'/module.json'))->toBeTrue()
        ->and(array_map(basename(...), File::directories($this->root)))->toBe(['acme-ownership'])
        ->and(Addon::query()->where('name', 'Ownership')->exists())->toBeTrue();
});

it('drops no table of a model without #[AddonModel]', function (): void {
    Schema::create('own_plain_widgets', function (Blueprint $table): void {
        $table->id();
    });
    ($this->installFixture)();

    app(AddonRegistry::class)->delete('Ownership', true);

    expect(Schema::hasTable('own_plain_widgets'))->toBeTrue()
        ->and(Schema::hasTable('own_widgets'))->toBeFalse();
});

it('skips a model carrying #[AddonModel] whose table is a core table, with a warning', function (): void {
    ($this->installFixture)();
    Log::spy();

    app(AddonRegistry::class)->delete('Ownership', true);

    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('own_widgets'))->toBeFalse();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, '"users"') && str_contains($message, 'Ownership'))
        ->once();
});

it('finishes the uninstall and leaves the staged files when they cannot be deleted after the data is dropped', function (): void {
    $dir = ($this->installFixture)();
    Log::spy();
    File::partialMock()
        ->shouldReceive('deleteDirectory')
        ->withArgs(fn (string $path): bool => str_contains($path, '.removing-'))
        ->andReturnFalse();
    File::shouldReceive('deleteDirectory')
        ->withArgs(fn (string $path): bool => !str_contains($path, '.removing-'))
        ->passthru();

    app(AddonRegistry::class)->delete('Ownership', true);

    expect(Addon::query()->where('name', 'Ownership')->exists())->toBeFalse()
        ->and(Schema::hasTable('own_widgets'))->toBeFalse()
        ->and(is_dir($dir))->toBeFalse()
        ->and(glob($this->root.'/.acme-ownership.removing-*'))->toHaveCount(1);
    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'remain at'))
        ->once();
});

it('never drops a core table the manifest lists, and logs a warning for each', function (): void {
    ($this->installFixture)(['database' => ['tables' => ['own_widget_tags', 'users', 'model_has_roles']]]);
    Log::spy();

    app(AddonRegistry::class)->delete('Ownership', true);

    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('model_has_roles'))->toBeTrue()
        ->and(Schema::hasTable('own_widget_tags'))->toBeFalse()
        ->and(Schema::hasTable('own_widgets'))->toBeFalse();

    foreach (['users', 'model_has_roles'] as $table) {
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, '"'.$table.'"') && str_contains($message, 'Ownership'))
            ->once();
    }
});

it('knows the core tables from migrations, models and configuration', function (): void {
    expect(CoreTables::all())->toContain('users', 'model_has_roles', 'migrations', 'failed_jobs');
});

it('calls onUninstall with the data flag before the files are staged', function (): void {
    $dir = ($this->installFixture)();
    OwnershipServiceProvider::$watchPath = $dir;

    app(AddonRegistry::class)->delete('Ownership', true);

    expect(OwnershipServiceProvider::$calls)->toBe([['onUninstall', true, true]]);
});

it('calls onUninstall without the data flag when the data is kept', function (): void {
    $dir = ($this->installFixture)();
    OwnershipServiceProvider::$watchPath = $dir;

    app(AddonRegistry::class)->delete('Ownership', false);

    expect(OwnershipServiceProvider::$calls)->toBe([['onUninstall', false, true]]);
});

it('finishes the uninstall when onUninstall throws', function (): void {
    ($this->installFixture)();
    OwnershipServiceProvider::$throwOn = 'onUninstall';

    app(AddonRegistry::class)->delete('Ownership', true);

    expect(Addon::query()->where('name', 'Ownership')->exists())->toBeFalse()
        ->and(Schema::hasTable('own_widgets'))->toBeFalse();
});

/**
 * Zip the ownership fixture, with the given version, for install/update.
 */
function zipOwnershipFixture(string $directory, string $version, string $namespace = 'PhpvmsOwnershipFixture'): string
{
    $fixture = realpath(__DIR__.'/../../fixtures/Addons/ownership');
    $path = $directory.'/ownership-'.$version.'.zip';

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach (File::allFiles($fixture) as $file) {
        $relative = $file->getRelativePathname();
        $contents = (string) file_get_contents($file->getPathname());

        if ($relative === 'module.json') {
            $contents = json_encode([...json_decode($contents, true), 'version' => $version]);
        }

        // A JSON file holds the namespace with doubled backslashes.
        $zip->addFromString($relative, str_replace(
            'PhpvmsOwnershipFixture',
            str_ends_with($relative, '.json') ? addslashes($namespace) : $namespace,
            $contents,
        ));
    }

    $zip->close();

    return $path;
}

it('calls onInstall after an install and onUpdate with both versions after an update', function (): void {
    // A namespace no process has loaded yet, so the provider class is first
    // loaded from the installed directory.
    $namespace = 'Acme\\Ownership'.bin2hex(random_bytes(4));
    $provider = $namespace.'\\Providers\\OwnershipServiceProvider';
    $registry = app(AddonRegistry::class);

    $registry->install(new ZipSource(zipOwnershipFixture($this->root, '1.0.0', $namespace)));
    $registry->update('Ownership', new ZipSource(zipOwnershipFixture($this->root, '2.0.0', $namespace)));

    expect($provider::$calls)->toBe([
        ['onInstall', realpath($this->root.'/acme-ownership')],
        ['onUpdate', '1.0.0', '2.0.0'],
    ]);
});

it('completes the update when onUpdate throws', function (): void {
    $registry = app(AddonRegistry::class);
    $registry->install(new ZipSource(zipOwnershipFixture($this->root, '1.0.0')));

    OwnershipServiceProvider::$throwOn = 'onUpdate';

    $registry->update('Ownership', new ZipSource(zipOwnershipFixture($this->root, '2.0.0')));

    expect(Addon::query()->where('name', 'Ownership')->value('version'))->toBe('2.0.0');
});
