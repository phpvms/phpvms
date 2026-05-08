<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PirepState: int implements HasColor, HasLabel
{
    use HasSelect;

    case IN_PROGRESS = 0;
    case PENDING = 1;
    case ACCEPTED = 2;
    case CANCELLED = 3;
    case DELETED = 4;
    case DRAFT = 5;
    case REJECTED = 6;
    case PAUSED = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::IN_PROGRESS => __('pireps.state.in_progress'),
            self::PENDING     => __('pireps.state.pending'),
            self::ACCEPTED    => __('pireps.state.accepted'),
            self::CANCELLED   => __('pireps.state.cancelled'),
            self::DELETED     => __('pireps.state.deleted'),
            self::DRAFT       => __('pireps.state.draft'),
            self::REJECTED    => __('pireps.state.rejected'),
            self::PAUSED      => __('pireps.state.paused'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'info',
            self::PENDING     => 'warning',
            self::ACCEPTED    => 'success',
            self::CANCELLED   => 'gray',
            self::DELETED     => 'danger',
            self::DRAFT       => 'gray',
            self::REJECTED    => 'danger',
            self::PAUSED      => 'warning',
        };
    }
}
