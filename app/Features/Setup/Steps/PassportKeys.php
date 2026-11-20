<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use phpseclib3\Crypt\RSA;
use RuntimeException;

final class PassportKeys implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::PassportKeys;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        // Keys supplied through env (multi-node, Octane) need nothing on disk.
        if (config('passport.private_key') && config('passport.public_key')) {
            return;
        }

        $publicKey = Passport::keyPath('oauth-public.key');
        $privateKey = Passport::keyPath('oauth-private.key');

        if (file_exists($publicKey) && file_exists($privateKey)) {
            return;
        }

        // Direct generation: passport:keys is only registered in console, not in the web runner.
        $key = RSA::createKey(4096);

        if (file_put_contents($publicKey, (string) $key->getPublicKey()) === false
            || file_put_contents($privateKey, (string) $key) === false) {
            throw new RuntimeException('Failed to write Passport encryption keys to '.dirname($privateKey));
        }

        if (!windows_os()) {
            @chmod($publicKey, 0660);
            @chmod($privateKey, 0600);
        }

        Log::info('Generated Passport encryption keys');
    }
}
