<?php

declare(strict_types=1);

namespace App\Http\Resources\RouteForge;

use App\Contracts\Resource;
use App\Services\RouteForge\CommitResult;
use Illuminate\Http\Request;

/**
 * Wire shape returned by /admin/route-forge/api/commit on success.
 *
 * Delegates to CommitResult::toArray(); the DTO owns the canonical wire
 * envelope. Keeping the shape there means controller/resource changes can
 * happen without re-aligning two JSON layouts.
 */
final class CommitResponseResource extends Resource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        /** @var CommitResult $result */
        $result = $this->resource;

        return $result->toArray();
    }
}
