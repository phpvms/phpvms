<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A third-party OAuth application holding at least one live token for this
 * user. Revoking works per client, not per token, which is why the tokens are
 * grouped by client_id before they reach here.
 */
#[TypeScript]
final class AuthorizedAppData extends Data
{
    /** @param list<string> $scopes union of the scopes across the client's live tokens */
    public function __construct(
        public string $clientId,
        public string $name,
        public array $scopes,
        public int $tokenCount,
        public ?string $lastAuthorizedAt,
    ) {}
}
