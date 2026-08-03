<?php

declare(strict_types=1);

namespace App\Addons\Support;

/**
 * Verifies the registry's `Registry-Signature` response header: an Ed25519
 * detached signature (base64) over the raw, uncompressed response body, checked
 * against the pinned base64-encoded 32-byte public key stored in settings.
 *
 * Fails closed — a missing header, malformed input, or unset/invalid pinned key
 * all return false, so an install never proceeds on an unverifiable response.
 */
class RegistrySignatureVerifier
{
    /**
     * @param string      $body            the raw, decoded response body bytes
     * @param string|null $signatureHeader the base64 `Registry-Signature` value
     */
    public function verify(string $body, ?string $signatureHeader): bool
    {
        return $this->reason($body, $signatureHeader) === null;
    }

    /**
     * Null when the signature verifies; otherwise a specific, admin-actionable
     * reason it did not — the failure modes (no header, malformed, no pinned
     * key, mismatch) are otherwise indistinguishable in the install log/bell.
     *
     * @param string      $body            the raw, decoded response body bytes
     * @param string|null $signatureHeader the base64 `Registry-Signature` value
     */
    public function reason(string $body, ?string $signatureHeader): ?string
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return 'The registry did not return a signature (Registry-Signature header missing) — it may be misconfigured or not signing releases yet.';
        }

        $signature = base64_decode(trim($signatureHeader), true);

        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return 'The registry signature was malformed.';
        }

        $publicKey = $this->publicKey();

        if ($publicKey === null) {
            return 'No registry public key is pinned — set one with `php artisan registry:set-key`.';
        }

        if (!sodium_crypto_sign_verify_detached($signature, $body, $publicKey)) {
            return 'The registry signature did not match the pinned public key — if you changed registries, update the pinned key with `php artisan registry:set-key`.';
        }

        return null;
    }

    /**
     * The pinned raw public key bytes, or null when unset/malformed.
     */
    private function publicKey(): ?string
    {
        // The `setting()` helper returns null (not throws) when the row is
        // absent, so a missing key naturally fails closed below.
        $encoded = (string) setting('registry.public_key');

        if ($encoded === '') {
            return null;
        }

        $raw = base64_decode(trim($encoded), true);

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        return $raw;
    }
}
