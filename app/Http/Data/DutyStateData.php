<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\PirepState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DutyStateData extends Data
{
    public function __construct(
        public string $state,
        public string $label,
        public string $color,
    ) {}

    public static function fromPirepState(?PirepState $state): self
    {
        return match ($state) {
            PirepState::IN_PROGRESS => new self('on_duty', 'On duty', 'success'),
            PirepState::PAUSED      => new self('paused', 'Paused', 'warning'),
            default                 => new self('off_duty', 'Off duty', 'neutral'),
        };
    }
}
