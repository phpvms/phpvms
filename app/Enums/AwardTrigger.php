<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum AwardTrigger: string implements HasLabel
{
    use HasSelect;

    case Pirep = 'pirep';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pirep => __('filament.award_trigger_pirep'),
            self::User  => __('filament.award_trigger_user'),
        };
    }
}
