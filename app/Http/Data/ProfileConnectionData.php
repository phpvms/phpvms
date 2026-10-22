<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One social login provider as the profile's "Connected accounts" card sees it.
 * Mirrors resources/views/layouts/seven/profile/connected-accounts.blade.php:
 * a linked provider offers unlink, an unlinked-but-connectable one offers link,
 * and a provider that is neither is listed with no action.
 */
#[TypeScript]
final class ProfileConnectionData extends Data
{
    public function __construct(
        public string $connectionId,
        public string $displayName,
        public bool $linked,
        public bool $linkable,
        /** The provider's own account id, shown for a linked provider. */
        public ?string $providerUserId,
    ) {}
}
