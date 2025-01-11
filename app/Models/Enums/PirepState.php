<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class PirepState extends Enum
{
    public const IN_PROGRESS = 0;  // flight is ongoing

    public const PENDING = 1;  // waiting admin approval

    public const ACCEPTED = 2;

    public const CANCELLED = 3;

    public const DELETED = 4;

    public const DRAFT = 5;

    public const REJECTED = 6;

    public const PAUSED = 7;

    protected static array $labels = [
        self::IN_PROGRESS => 'pireps.state.in_progress',
        self::PENDING     => 'pireps.state.pending',
        self::ACCEPTED    => 'pireps.state.accepted',
        self::CANCELLED   => 'pireps.state.cancelled',
        self::DELETED     => 'pireps.state.deleted',
        self::DRAFT       => 'pireps.state.draft',
        self::REJECTED    => 'pireps.state.rejected',
        self::PAUSED      => 'pireps.state.paused',
    ];
}
