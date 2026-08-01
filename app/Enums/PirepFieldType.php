<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Value type of a pirep custom field, as declared by ACARS. A null/absent type
 * means an untyped legacy value — treat it as plain text.
 */
enum PirepFieldType: string
{
    case NUMBER = 'NUMBER';
    case TEXT = 'TEXT';
    case TIMESTAMP = 'TIMESTAMP';
    case BOOLEAN = 'BOOLEAN';
}
