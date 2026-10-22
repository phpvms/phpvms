<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Award;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One pilot award for the profile page. */
#[TypeScript]
final class AwardData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $image,
    ) {}

    public static function fromModel(Award $award): self
    {
        // `Award::description()` stores plain text, so a description with
        // nothing in it is genuinely empty — no markup to see through. Null
        // rather than "" is what lets the "Qualifier unavailable" fallback
        // fire on the page.
        return new self(
            name: $award->name,
            description: blank($award->description) ? null : $award->description,
            image: $award->image_url,
        );
    }
}
