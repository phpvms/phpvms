<?php

declare(strict_types=1);

use App\Addons\Support\RegistrySignatureVerifier;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;

/**
 * Pin a fresh public key and return the matching secret key so the test can
 * produce valid signatures.
 */
function seedRegistryKeypair(): string
{
    $keypair = sodium_crypto_sign_keypair();
    $public = sodium_crypto_sign_publickey($keypair);

    DB::table('settings')->updateOrInsert(
        ['id' => 'registry_public_key'],
        [
            'key'   => 'registry.public_key',
            'name'  => 'Registry Public Key',
            'value' => base64_encode($public),
            'group' => 'general',
            'type'  => 'hidden',
        ],
    );

    app(SettingService::class)->clearMemo();

    return sodium_crypto_sign_secretkey($keypair);
}

it('verifies a valid detached signature', function (): void {
    $secret = seedRegistryKeypair();
    $body = '{"name":"phpvms/acars","version":"2.2.0"}';
    $sig = base64_encode(sodium_crypto_sign_detached($body, $secret));

    expect(new RegistrySignatureVerifier()->verify($body, $sig))->toBeTrue();
});

it('rejects a signature over a different body', function (): void {
    $secret = seedRegistryKeypair();
    $sig = base64_encode(sodium_crypto_sign_detached('original', $secret));

    expect(new RegistrySignatureVerifier()->verify('tampered', $sig))->toBeFalse();
});

it('rejects a missing signature header', function (): void {
    seedRegistryKeypair();

    expect(new RegistrySignatureVerifier()->verify('body', null))->toBeFalse();
});

it('fails closed when no public key is pinned', function (): void {
    $secret = seedRegistryKeypair();
    app(SettingService::class)->store('registry.public_key', '');

    $sig = base64_encode(sodium_crypto_sign_detached('body', $secret));

    expect(new RegistrySignatureVerifier()->verify('body', $sig))->toBeFalse();
});

it('reports a distinct reason for each failure mode', function (): void {
    $secret = seedRegistryKeypair();
    $verifier = new RegistrySignatureVerifier();

    // Missing header vs a valid signature over a different body (key mismatch).
    expect($verifier->reason('body', null))->toContain('did not return a signature');

    $sig = base64_encode(sodium_crypto_sign_detached('original', $secret));
    expect($verifier->reason('tampered', $sig))->toContain('did not match the pinned public key');

    // Valid signature verifies -> null reason.
    $good = base64_encode(sodium_crypto_sign_detached('body', $secret));
    expect($verifier->reason('body', $good))->toBeNull();
});
