<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

class PageType extends Enum
{
    public const PAGE = 0;

    public const LINK = 1;

    public static array $labels = [
        self::PAGE => 'Page',
        self::LINK => 'Link',
    ];
}
