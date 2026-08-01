<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

/**
 * Value type of a pirep custom field, as declared by ACARS. A null/absent type
 * means an untyped legacy value — treat it as plain text.
 */
enum PirepFieldType: string implements HasLabel
{
    use HasSelect;

    case NUMBER = 'NUMBER';
    case TEXT = 'TEXT';
    case TIMESTAMP = 'TIMESTAMP';
    case BOOLEAN = 'BOOLEAN';

    public function getLabel(): string
    {
        return match ($this) {
            self::NUMBER    => 'Number',
            self::TEXT      => 'Text',
            self::TIMESTAMP => 'Timestamp',
            self::BOOLEAN   => 'Boolean',
        };
    }
}
