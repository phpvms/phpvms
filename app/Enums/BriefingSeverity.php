<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BriefingSeverity: string implements HasColor, HasLabel
{
    case Info = 'info';
    case Light = 'light';
    case Moderate = 'moderate';
    case Severe = 'severe';

    public function getLabel(): string
    {
        return match ($this) {
            self::Info     => __('briefing.severity.info'),
            self::Light    => __('briefing.severity.light'),
            self::Moderate => __('briefing.severity.moderate'),
            self::Severe   => __('briefing.severity.severe'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Severe            => 'danger',
            self::Moderate          => 'warning',
            self::Light, self::Info => 'gray',
        };
    }
}
