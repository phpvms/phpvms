<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserState: int implements HasColor, HasLabel
{
    use HasSelect;

    case PENDING = 0;
    case ACTIVE = 1;
    case REJECTED = 2;
    case ON_LEAVE = 3;
    case SUSPENDED = 4;
    case DELETED = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING   => __('user.state.pending'),
            self::ACTIVE    => __('user.state.active'),
            self::REJECTED  => __('user.state.rejected'),
            self::ON_LEAVE  => __('user.state.on_leave'),
            self::SUSPENDED => __('user.state.suspended'),
            self::DELETED   => __('user.state.deleted'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING   => 'warning',
            self::ACTIVE    => 'success',
            self::REJECTED  => 'danger',
            self::ON_LEAVE  => 'info',
            self::SUSPENDED => 'danger',
            self::DELETED   => 'gray',
        };
    }
}
