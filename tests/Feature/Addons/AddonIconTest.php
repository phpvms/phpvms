<?php

declare(strict_types=1);

use App\Features\Addons\Models\AddonBootCache;
use App\Features\Addons\Support\AddonIcon;
use App\Features\Addons\Support\ManifestParser;
use App\Models\Addon;
use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    Addon::query()->delete();

    $this->dir = sys_get_temp_dir().'/addon-icon-'.uniqid();
    File::ensureDirectoryExists($this->dir.'/assets');
    File::put($this->dir.'/assets/icon.png', 'png-bytes');
    File::put($this->dir.'/assets/notes.txt', 'text');
    File::put($this->dir.'/composer.json', '{}');
    File::put(sys_get_temp_dir().'/addon-icon-outside.png', 'outside');
});

afterEach(function (): void {
    File::deleteDirectory($this->dir);
    File::delete(sys_get_temp_dir().'/addon-icon-outside.png');
});

function addonIconManifest(string $dir, mixed $icon): void
{
    File::put($dir.'/addon.json', json_encode([
        'name'        => 'IconDemo',
        'registry_id' => 'acme/icon-demo',
        'providers'   => [],
        'icon'        => $icon,
    ]));
}

test('the parser reads the manifest icon and the boot cache keeps it', function (): void {
    addonIconManifest($this->dir, ' assets/icon.png ');

    $manifest = new ManifestParser()->parse($this->dir);
    expect($manifest->icon)->toBe('assets/icon.png');

    $entry = AddonBootCache::fromArray(['name' => 'IconDemo', 'icon' => $manifest->icon]);
    expect($entry->icon)->toBe('assets/icon.png')
        ->and($entry->toArray()['icon'])->toBe('assets/icon.png');
});

test('a blank or non-string icon parses as null', function (mixed $icon): void {
    addonIconManifest($this->dir, $icon);

    expect(new ManifestParser()->parse($this->dir)->icon)->toBeNull();
})->with([[''], ['   '], [42], [null]]);

test('an http(s) icon is used as is', function (): void {
    expect(AddonIcon::url('IconDemo', $this->dir, 'https://cdn.test/icon.png'))->toBe('https://cdn.test/icon.png');
});

test('a local image inside the addon gets the icon route', function (): void {
    expect(AddonIcon::url('IconDemo', $this->dir, 'assets/icon.png'))->toBe(route('addons.icon', ['addon' => 'IconDemo']));
});

test('an unsafe, missing or non-image local icon gets no url', function (string $icon): void {
    expect(AddonIcon::url('IconDemo', $this->dir, $icon))->toBeNull();
})->with([
    'parent path'   => ['../addon-icon-outside.png'],
    'absolute path' => ['/etc/hosts'],
    'other scheme'  => ['javascript:alert(1)'],
    'missing file'  => ['assets/missing.png'],
    'not an image'  => ['assets/notes.txt'],
]);

test('the icon route serves the declared local icon to a signed-in user', function (): void {
    addonIconManifest($this->dir, 'assets/icon.png');
    Addon::factory()->create(['name' => 'IconDemo', 'path' => $this->dir]);

    $response = $this->actingAs(User::factory()->create())->get(route('addons.icon', ['addon' => 'IconDemo']));

    $response->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('the icon route 404s an unknown addon or an icon it may not serve', function (): void {
    addonIconManifest($this->dir, 'assets/notes.txt');
    Addon::factory()->create(['name' => 'IconDemo', 'path' => $this->dir]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('addons.icon', ['addon' => 'IconDemo']))->assertNotFound();
    $this->actingAs($user)->get(route('addons.icon', ['addon' => 'Nope']))->assertNotFound();
});

test('the icon route needs a signed-in user', function (): void {
    $this->get(route('addons.icon', ['addon' => 'IconDemo']))->assertRedirect();
});
