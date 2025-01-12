<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class UserState extends Enum
{
    public const PENDING = 0;

    public const ACTIVE = 1;

    public const REJECTED = 2;

    public const ON_LEAVE = 3;

    public const SUSPENDED = 4;

    public const DELETED = 5;

    protected static array $labels = [
        self::PENDING   => 'user.state.pending',
        self::ACTIVE    => 'user.state.active',
        self::REJECTED  => 'user.state.rejected',
        self::ON_LEAVE  => 'user.state.on_leave',
        self::SUSPENDED => 'user.state.suspended',
        self::DELETED   => 'user.state.deleted',
    ];
}
