<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\PirepEvent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One classified event (milestone, phase change, violation, ...) along a flown route. */
#[TypeScript]
final class MapEventData extends Data
{
    public function __construct(
        public ?string $type,
        public ?string $phase,
        public ?float $lat,
        public ?float $lon,
        public ?float $altitude,
        public ?string $occurredAt,
    ) {}

    public static function fromModel(PirepEvent $event): self
    {
        return new self(
            type: $event->type,
            phase: $event->phase,
            lat: $event->lat,
            lon: $event->lon,
            altitude: $event->altitude_msl,
            occurredAt: $event->created_at?->toIso8601String(),
        );
    }
}
