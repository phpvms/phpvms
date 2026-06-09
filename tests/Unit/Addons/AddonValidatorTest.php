<?php

declare(strict_types=1);

use App\Addons\Models\AddonManifest;
use App\Addons\Support\AddonValidator;
use App\Exceptions\AddonInstallException;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/validate-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    $this->validator = app(AddonValidator::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->dir);
});

it('returns the manifest for a valid addon', function (): void {
    File::put($this->dir.'/module.json', json_encode([
        'name'      => 'Demo',
        'providers' => [],
    ]));
    File::put($this->dir.'/composer.json', json_encode([
        'autoload' => ['psr-4' => ['Modules\\Demo\\' => '']],
    ]));

    expect($this->validator->validate($this->dir))->toBeInstanceOf(AddonManifest::class);
});

it('throws when module.json is missing', function (): void {
    $this->validator->validate($this->dir);
})->throws(AddonInstallException::class);
