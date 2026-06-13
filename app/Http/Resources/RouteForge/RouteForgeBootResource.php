<?php

declare(strict_types=1);

namespace App\Http\Resources\RouteForge;

use App\Contracts\Resource;
use Illuminate\Http\Request;
use Override;

/**
 * Wire shape returned by /admin/route-forge/api/boot.
 *
 * The RouteForge SPA fetches this envelope once at mount time (replacing the
 * old `window.routeforgeConfig` global) and seeds its in-memory store from
 * it. All subsequent calls into the other RouteForge admin endpoints read
 * the CSRF token + route URLs from the hydrated store.
 *
 * The resource's $this->resource is the plain associative array assembled
 * by the controller's `boot()` action — no Eloquent model wraps these
 * mount-time aggregates.
 *
 * @phpstan-type BootEnvelope array{
 *     csrf_token: string,
 *     locale: string,
 *     user: array{id: int|null, name: string|null, can_commit: bool},
 *     airlines: list<array{id: int, name: string, icao: string|null, iata: string|null}>,
 *     routes: array<string, string>,
 *     config: array<string, mixed>,
 *     translations: array<string, mixed>,
 * }
 */
final class RouteForgeBootResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $envelope */
        $envelope = $this->resource;

        return [
            'csrf_token'   => (string) ($envelope['csrf_token'] ?? ''),
            'locale'       => (string) ($envelope['locale'] ?? ''),
            'user'         => $envelope['user'] ?? [],
            'airlines'     => array_values($envelope['airlines'] ?? []),
            'routes'       => $envelope['routes'] ?? [],
            'config'       => $envelope['config'] ?? [],
            'translations' => $envelope['translations'] ?? [],
        ];
    }
}
