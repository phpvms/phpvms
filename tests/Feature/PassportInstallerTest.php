<?php

declare(strict_types=1);

use App\Services\Installer\InstallerService;
use Illuminate\Support\Facades\Artisan;

/*
 * InstallerService::ensurePassportKeys() — the installer/updater hook that
 * provisions Passport signing keys automatically. Artisan is mocked and a temp
 * storage path is used, so the real storage/oauth-*.key files are never touched.
 */

function useEmptyStoragePath(): string
{
    $tmp = sys_get_temp_dir().'/phpvms-passport-'.uniqid('', true);
    mkdir($tmp);
    app()->useStoragePath($tmp);

    return $tmp;
}

it('generates keys when none are configured or present', function (): void {
    config(['passport.private_key' => null, 'passport.public_key' => null]);
    useEmptyStoragePath();

    Artisan::shouldReceive('call')->once()->with('passport:keys', ['--force' => true]);

    app(InstallerService::class)->ensurePassportKeys();
});

it('does not generate keys when provided via env', function (): void {
    config(['passport.private_key' => 'private-pem', 'passport.public_key' => 'public-pem']);

    Artisan::shouldReceive('call')->never();

    app(InstallerService::class)->ensurePassportKeys();
});

it('does not regenerate keys that already exist on disk', function (): void {
    config(['passport.private_key' => null, 'passport.public_key' => null]);
    $tmp = useEmptyStoragePath();
    touch($tmp.'/oauth-private.key');
    touch($tmp.'/oauth-public.key');

    Artisan::shouldReceive('call')->never();

    app(InstallerService::class)->ensurePassportKeys();
});
