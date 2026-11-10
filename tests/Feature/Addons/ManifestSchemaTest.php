<?php

declare(strict_types=1);

use App\Addons\AddonRegistry;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Sources\ZipSource;
use App\Addons\Support\ManifestSchema;
use App\Addons\Support\ManifestSchemaValidator;
use App\Exceptions\AddonInstallException;
use App\Models\Addon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const SCHEMA_URL = 'https://addons.test/schema/addon.json';

/**
 * @return array<string, mixed>
 */
function validManifest(array $overrides = []): array
{
    return [...[
        'schema_version' => 1,
        'name'           => 'Demo',
        'registry_id'    => 'acme/demo',
        'type'           => 'module',
        'description'    => 'A demo addon.',
        'providers'      => [],
    ], ...$overrides];
}

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->work = sys_get_temp_dir().'/manifest-schema-'.uniqid();
    File::ensureDirectoryExists($this->work);

    $this->schemaBody = (string) file_get_contents(base_path('tests/fixtures/Addons/addon.schema.json'));
    $this->schemaPath = $this->work.'/schema/addon.schema.json';

    // Cache path in a temp dir with the real TTL logic, so the download path is exercised.
    Config::set('addons.schema.url', SCHEMA_URL);
    Config::set('addons.schema.path', $this->schemaPath);
    Config::set('addons.schema.ttl', 3600);
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

describe('schema cache', function (): void {
    it('does not throw when the downloaded schema cannot be written', function (): void {
        Log::spy();
        Http::fake([SCHEMA_URL => Http::response($this->schemaBody)]);
        // A regular file where the cache directory should be makes every write fail.
        File::put($this->work.'/blocker', '');
        Config::set('addons.schema.path', $this->work.'/blocker/addon.schema.json');

        expect(new ManifestSchema()->schema())->toBeNull();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'could not write'))->once();
    });

    it('downloads the schema and writes the file when there is none', function (): void {
        Http::fake([SCHEMA_URL => Http::response($this->schemaBody)]);

        $schema = new ManifestSchema()->schema();

        expect($schema)->not->toBeNull()
            ->and(File::exists($this->schemaPath))->toBeTrue();
        Http::assertSent(fn (Request $request): bool => $request->url() === SCHEMA_URL);
    });

    it('does not download while the file is fresh', function (): void {
        File::ensureDirectoryExists(dirname($this->schemaPath));
        File::put($this->schemaPath, $this->schemaBody);
        Http::fake();

        expect(new ManifestSchema()->schema())->not->toBeNull();
        Http::assertNothingSent();
    });

    it('keeps the old file and warns when a stale file cannot be refreshed', function (): void {
        File::ensureDirectoryExists(dirname($this->schemaPath));
        File::put($this->schemaPath, $this->schemaBody);
        touch($this->schemaPath, time() - 7200);
        Http::fake([SCHEMA_URL => Http::response('down', 503)]);
        Log::spy();

        $schema = new ManifestSchema()->schema();

        expect($schema)->not->toBeNull()
            ->and(File::get($this->schemaPath))->toBe($this->schemaBody);
        Log::shouldHaveReceived('warning')->once();
    });

    it('does not replace the file with a body that is not a JSON object', function (): void {
        File::ensureDirectoryExists(dirname($this->schemaPath));
        File::put($this->schemaPath, $this->schemaBody);
        touch($this->schemaPath, time() - 7200);
        Http::fake([SCHEMA_URL => Http::response('<html>oops</html>')]);

        expect(new ManifestSchema()->schema())->not->toBeNull()
            ->and(File::get($this->schemaPath))->toBe($this->schemaBody);
    });

    it('returns no schema and accepts any manifest when there is no file and the download fails', function (): void {
        Http::fake([SCHEMA_URL => Http::response('down', 500)]);
        Log::spy();

        expect(new ManifestSchema()->schema())->toBeNull()
            ->and(app(ManifestSchemaValidator::class)->errors([]))->toBe([]);
        Log::shouldHaveReceived('warning')->atLeast()->once();
    });
});

describe('validation', function (): void {
    beforeEach(function (): void {
        File::ensureDirectoryExists(dirname($this->schemaPath));
        File::put($this->schemaPath, $this->schemaBody);
    });

    it('accepts a valid manifest', function (): void {
        expect(app(ManifestSchemaValidator::class)->errors(validManifest()))->toBe([]);
    });

    it('names the property of each failure', function (): void {
        $manifest = validManifest(['description' => '', 'registry_id' => 'no-slash']);
        unset($manifest['schema_version']);

        $errors = implode("\n", app(ManifestSchemaValidator::class)->errors($manifest));

        expect($errors)->toContain('description')
            ->and($errors)->toContain('registry_id')
            ->and($errors)->toContain('schema_version');
    });

    it('rejects an install whose addon.json has an empty description', function (): void {
        $zipPath = $this->work.'/demo.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('addon.json', json_encode(validManifest(['description' => ''])));
        $zip->addFromString('composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\Demo\\' => '']]]));
        $zip->close();
        Config::set('addons.paths.root', $this->work.'/root');

        try {
            app(AddonRegistry::class)->install(new ZipSource($zipPath));
            $this->fail('install should have been rejected');
        } catch (AddonInstallException $addonInstallException) {
            expect($addonInstallException->getMessage())->toContain('description');
        }

        expect(Addon::query()->where('registry_id', 'acme/demo')->exists())->toBeFalse();
    });

    it('skips a local addon without schema_version and warns naming it', function (): void {
        $base = $this->work.'/root';
        Config::set('addons.paths.root', $base);

        foreach (['good' => validManifest(['name' => 'Good', 'registry_id' => 'acme/good']), 'bad' => (static function (): array {
            $manifest = validManifest(['name' => 'Bad', 'registry_id' => 'acme/bad']);
            unset($manifest['schema_version']);

            return $manifest;
        })()] as $dir => $manifest) {
            File::ensureDirectoryExists($base.'/'.$dir);
            File::put($base.'/'.$dir.'/addon.json', json_encode($manifest));
            File::put($base.'/'.$dir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\'.ucfirst($dir).'\\' => '']]]));
        }

        Log::spy();

        $found = app(AddonDiscoveryService::class)->discoverNewAddons()->pluck('registry_id')->all();

        expect($found)->toContain('acme/good')
            ->and($found)->not->toContain('acme/bad');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'Bad') && str_contains($message, 'schema_version'))
            ->once();
    });
});
