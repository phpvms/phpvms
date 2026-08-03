<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SettingService;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'registry:set-key', description: 'Set or rotate the pinned registry Ed25519 public key')]
#[Signature('registry:set-key
                    {key : Base64-encoded 32-byte raw Ed25519 public key}')]
class RegistrySetKey extends Command
{
    /**
     * Set/rotate the registry public key used to verify signed registry
     * responses. The recovery path when the key shipped in a release is wrong
     * or needs rotating — hidden settings have no admin UI.
     */
    public function handle(SettingService $settings): int
    {
        $key = trim((string) $this->argument('key'));
        $raw = base64_decode($key, true);

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            $this->components->error('Key must be a base64-encoded 32-byte raw Ed25519 public key.');

            return self::FAILURE;
        }

        if ($settings->store('registry.public_key', $key) === null) {
            $this->components->error('The registry.public_key setting row does not exist; run migrations first.');

            return self::FAILURE;
        }

        $this->components->info('Registry public key updated.');

        return self::SUCCESS;
    }
}
