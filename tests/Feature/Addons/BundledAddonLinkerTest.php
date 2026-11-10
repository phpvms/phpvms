<?php

declare(strict_types=1);

use App\Addons\Support\BundledAddonLinker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

/**
 * BundledAddonLinker links each bundled addon from the bundled source into
 * the addon root. Both are temp directories here.
 */
beforeEach(function (): void {
    $this->work = sys_get_temp_dir().'/bundled-linker-'.uniqid('', true);
    $this->source = $this->work.'/modules';
    $this->root = $this->work.'/storage/app/addons';

    File::ensureDirectoryExists($this->source);

    Config::set('addons.paths.root', $this->root);
    Config::set('addons.paths.bundled', $this->source);
    Config::set('addons.bundled', ['shipped']);
});

afterEach(function (): void {
    File::deleteDirectory($this->work);
});

function linkerAddon(string $dir, string $version): void
{
    File::ensureDirectoryExists($dir);
    File::put($dir.'/addon.json', json_encode(addonManifest([
        'name'        => 'Shipped',
        'registry_id' => 'acme/shipped',
        'version'     => $version,
        'providers'   => [],
    ])));
    File::put($dir.'/composer.json', json_encode(['autoload' => ['psr-4' => ['Modules\\Shipped\\' => 'app/']]]));
}

it('links a missing bundled addon with a relative symlink', function (): void {
    linkerAddon($this->source.'/shipped', '1.0.0');

    expect(app(BundledAddonLinker::class)->link())->toBe(['shipped' => BundledAddonLinker::LINKED])
        ->and(readlink($this->root.'/shipped'))->toBe('../../../modules/shipped')
        ->and(realpath($this->root.'/shipped'))->toBe(realpath($this->source.'/shipped'));
});

it('leaves an existing link alone', function (): void {
    linkerAddon($this->source.'/shipped', '1.0.0');
    app(BundledAddonLinker::class)->link();

    expect(app(BundledAddonLinker::class)->link())->toBe(['shipped' => BundledAddonLinker::PRESENT]);
});

it('keeps a registry update that is newer than the bundled copy', function (): void {
    linkerAddon($this->source.'/shipped', '1.0.0');
    linkerAddon($this->root.'/shipped', '1.1.0');

    expect(app(BundledAddonLinker::class)->link())->toBe(['shipped' => BundledAddonLinker::KEPT])
        ->and(is_link($this->root.'/shipped'))->toBeFalse();
});

it('replaces a registry update with the link once the bundled copy catches up', function (string $installed): void {
    linkerAddon($this->source.'/shipped', '1.1.0');
    linkerAddon($this->root.'/shipped', $installed);

    expect(app(BundledAddonLinker::class)->link())->toBe(['shipped' => BundledAddonLinker::RESTORED])
        ->and(is_link($this->root.'/shipped'))->toBeTrue()
        ->and(File::exists($this->source.'/shipped/addon.json'))->toBeTrue()
        // The replaced copy is not left in the root for discovery to find.
        ->and(array_values(array_diff(scandir($this->root), ['.', '..'])))->toBe(['shipped']);
})->with(['equal version' => '1.1.0', 'older version' => '1.0.0']);

it('skips a bundled addon whose files are not in the release', function (): void {
    expect(app(BundledAddonLinker::class)->link())->toBe([])
        ->and(file_exists($this->root.'/shipped'))->toBeFalse();
});

it('refuses to replace a file in the way of a link', function (): void {
    linkerAddon($this->source.'/shipped', '1.0.0');
    File::ensureDirectoryExists($this->root);
    File::put($this->root.'/shipped', 'not an addon');

    expect(fn () => app(BundledAddonLinker::class)->link())->toThrow(RuntimeException::class)
        ->and(File::get($this->root.'/shipped'))->toBe('not an addon');
});
