<?php

declare(strict_types=1);

namespace App\Support\Settings;

/**
 * The value type of a setting, derived from its settings-class property type.
 */
enum SettingType: string
{
    case Bool = 'bool';
    case Int = 'int';
    case Float = 'float';
    case String = 'string';
    case Date = 'date';
    case Enum = 'enum';

    /**
     * The type name the settings table stored before the move to typed
     * settings classes, kept for the settings listing resource.
     */
    public function legacyName(): string
    {
        return match ($this) {
            self::Bool   => 'boolean',
            self::Int    => 'int',
            self::Float  => 'float',
            self::String => 'text',
            self::Date   => 'date',
            self::Enum   => 'select',
        };
    }
}
