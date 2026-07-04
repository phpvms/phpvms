<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One comment on the dashboard last flight. */
#[TypeScript]
final class PirepCommentData extends Data
{
    public function __construct(
        public int $id,
        public ?string $comment,
        public ?string $created_at,
    ) {}
}
