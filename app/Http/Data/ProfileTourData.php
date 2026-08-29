<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Features\Tour\Models\UserTour;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One completed tour run on the profile page. Rendered entirely from the
 * run's own snapshot (`name`/`description`) rather than `bundle()`, which can
 * resolve to null once the bundle is deleted — see the user_tours create
 * migration docblock. The image is the only field that reaches for the
 * bundle, and does so defensively.
 */
#[TypeScript]
final class ProfileTourData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $image,
        public int $legs,
        public ?string $completedAt,
    ) {}

    public static function fromModel(UserTour $tour): self
    {
        return new self(
            id: $tour->id,
            name: $tour->name,
            description: $tour->description,
            image: $tour->bundle?->image_url,
            legs: $tour->legs_total,
            completedAt: $tour->completed_at?->toIso8601String(),
        );
    }
}
