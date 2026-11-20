<?php

declare(strict_types=1);

use App\Features\Setup\Steps\PassportKeys;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\Artisan;

/*
 * The PassportKeys setup step — the installer/updater hook that
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

function passportContext(): StepContext
{
    return new StepContext(false, function (string $line): void {}, function (int $done, int $total, ?string $label): void {});
}

it('generates keys when none are configured or present', function (): void {
    config(['passport.private_key' => null, 'passport.public_key' => null]);
    $tmp = useEmptyStoragePath();

    // Keys are generated in-process (phpseclib), NOT via `passport:keys` —
    // that command is unregistered outside console and the installer runs on the web.
    Artisan::shouldReceive('call')->with('passport:keys', Mockery::any())->never();

    new PassportKeys()->run(passportContext());

    expect(file_exists($tmp.'/oauth-private.key'))->toBeTrue()
        ->and(file_exists($tmp.'/oauth-public.key'))->toBeTrue()
        ->and(file_get_contents($tmp.'/oauth-private.key'))->toContain('PRIVATE KEY')
        ->and(file_get_contents($tmp.'/oauth-public.key'))->toContain('PUBLIC KEY');
});

it('does not generate keys when provided via env', function (): void {
    config(['passport.private_key' => 'private-pem', 'passport.public_key' => 'public-pem']);

    Artisan::shouldReceive('call')->never();

    new PassportKeys()->run(passportContext());
});

it('does not regenerate keys that already exist on disk', function (): void {
    config(['passport.private_key' => null, 'passport.public_key' => null]);
    $tmp = useEmptyStoragePath();
    touch($tmp.'/oauth-private.key');
    touch($tmp.'/oauth-public.key');

    Artisan::shouldReceive('call')->never();

    new PassportKeys()->run(passportContext());
});
