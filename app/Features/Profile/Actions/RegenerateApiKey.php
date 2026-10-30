<?php

declare(strict_types=1);

namespace App\Features\Profile\Actions;

use App\Models\User;
use App\Support\Utils;
use Illuminate\Support\Facades\Log;

/**
 * Replace a pilot's legacy API key.
 */
final class RegenerateApiKey
{
    public function handle(User $user): string
    {
        Log::info('Regenerating API key "'.$user->ident.'"');

        $user->api_key = Utils::generateApiKey();
        $user->save();

        return $user->api_key;
    }
}
