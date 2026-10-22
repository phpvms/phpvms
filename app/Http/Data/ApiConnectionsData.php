<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\User;
use App\Support\ApiScope;
use Illuminate\Support\Collection;
use Laravel\Passport\Token;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the profile's API connections drawer renders: the pilot's personal
 * access tokens, the third-party applications holding tokens for them, and the
 * scope catalog the create form offers.
 *
 * Attached as an Inertia optional prop, so it is only built when the drawer
 * asks for it -- the scope catalog and a token sweep are not worth paying for
 * on every profile view.
 *
 * Mirrors what ProfileController::connections() hands the Blade page, so the
 * two front ends stay one read path.
 */
#[TypeScript]
final class ApiConnectionsData extends Data
{
    /**
     * @param list<ApiTokenData>      $personalTokens
     * @param list<AuthorizedAppData> $authorizedApps
     * @param list<SelectOptionData>  $scopes         scope name => human description
     */
    public function __construct(
        public array $personalTokens,
        public array $authorizedApps,
        public array $scopes,
    ) {}

    public static function fromUser(User $user): self
    {
        $tokens = $user->tokens()
            ->where('revoked', false)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('client')
            ->latest('created_at')
            ->get()
            // A token whose client row is gone cannot be described or revoked
            // by client, so it is not shown -- same guard the Blade page uses.
            ->filter(fn ($token): bool => $token->client !== null);

        return new self(
            personalTokens: self::personalTokens($tokens),
            authorizedApps: self::authorizedApps($tokens),
            scopes: SelectOptionData::fromMap(ApiScope::catalog()),
        );
    }

    /**
     * @param  Collection<int, Token> $tokens
     * @return list<ApiTokenData>
     */
    private static function personalTokens(Collection $tokens): array
    {
        return $tokens
            ->filter(fn ($token): bool => $token->client->hasGrantType('personal_access'))
            ->map(fn ($token): ApiTokenData => new ApiTokenData(
                id: (string) $token->id,
                name: (string) ($token->name ?? 'Token'),
                scopes: array_values((array) ($token->scopes ?? [])),
                createdAt: $token->created_at?->toIso8601String(),
                expiresAt: $token->expires_at?->toIso8601String(),
            ))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Token>  $tokens
     * @return list<AuthorizedAppData>
     */
    private static function authorizedApps(Collection $tokens): array
    {
        return $tokens
            ->reject(fn ($token): bool => $token->client->hasGrantType('personal_access'))
            ->groupBy('client_id')
            ->map(function (Collection $clientTokens): AuthorizedAppData {
                $first = $clientTokens->first();

                return new AuthorizedAppData(
                    clientId: (string) $first->client_id,
                    name: (string) ($first->client->name ?? 'Application'),
                    // One client can hold several tokens with different grants;
                    // the drawer revokes all of them together, so show the union.
                    scopes: $clientTokens
                        ->flatMap(fn ($token): array => array_values((array) ($token->scopes ?? [])))
                        ->unique()
                        ->values()
                        ->all(),
                    tokenCount: $clientTokens->count(),
                    lastAuthorizedAt: $clientTokens
                        ->max(fn ($token) => $token->created_at)?->toIso8601String(),
                );
            })
            ->values()
            ->all();
    }
}
