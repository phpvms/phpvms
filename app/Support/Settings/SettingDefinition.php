<?php

declare(strict_types=1);

namespace App\Support\Settings;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\LaravelSettings\Settings;
use Stringable;
use Throwable;

/**
 * One property of a settings class, as the helpers, the settings pages and the
 * value copy see it: its key, type, default and presentation.
 *
 * Built by {@see SettingsRegistry} from the class by reflection. Immutable, so a
 * worker can keep the definitions for its whole lifetime.
 */
final readonly class SettingDefinition
{
    /**
     * @param class-string<Settings>        $class    The settings class holding the property
     * @param string                        $group    The storage group, `$class::group()`
     * @param string                        $section  The settings-page section the setting belongs to
     * @param string                        $key      The address callers use: `group.name` for core, the name for addons
     * @param class-string<BackedEnum>|null $enum     The enum class of an {@see SettingType::Enum} setting
     * @param int                           $order    The property's position within its class
     * @param string|null                   $labelKey Explicit label, or null to derive it
     */
    public function __construct(
        public string $class,
        public string $group,
        public string $section,
        public string $key,
        public string $name,
        public SettingType $type,
        public bool $nullable,
        public mixed $default,
        public ?string $enum,
        public bool $hidden,
        public bool $encrypted,
        public int $order,
        private ?string $labelKey,
        private ?string $descriptionKey,
        private ?string $translationPrefix,
    ) {}

    /**
     * The key with dots collapsed to underscores, as the settings table used
     * for its row ids.
     */
    public function id(): string
    {
        return self::formatKey($this->key);
    }

    /**
     * Lower-case a key and collapse dots to underscores, so `general.site_name`
     * and `general_site_name` address the same setting, as they always have.
     */
    public static function formatKey(string $key): string
    {
        return str_replace('.', '_', strtolower($key));
    }

    public function label(): string
    {
        if ($this->labelKey !== null) {
            return (string) __($this->labelKey);
        }

        return $this->translate('label') ?? Str::headline($this->name);
    }

    public function description(): ?string
    {
        if ($this->descriptionKey !== null) {
            return (string) __($this->descriptionKey);
        }

        return $this->translate('description');
    }

    /**
     * The value as the `setting()` helpers hand it out: an enum as its backing
     * value, so callers comparing or concatenating strings keep working.
     */
    public function present(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * The value as it is stored in the payload column, before encryption.
     * Mirrors the package's enum and date casts.
     */
    public function toPayload(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum        => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default                             => $value,
        };
    }

    /**
     * The value as the settings table stored it as text.
     */
    public function toLegacyString(mixed $value): string
    {
        return match (true) {
            is_bool($value)                     => $value ? '1' : '0',
            $value instanceof BackedEnum        => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            default                             => (string) $value,
        };
    }

    /**
     * Cast a value written by code or a form to the setting's type.
     *
     * @throws InvalidArgumentException when the value cannot represent the type
     */
    public function cast(mixed $value): mixed
    {
        return match ($this->type) {
            SettingType::Bool   => $this->castBool($value),
            SettingType::Int    => $this->castInt($value),
            SettingType::Float  => $this->castFloat($value),
            SettingType::String => $this->castString($value),
            SettingType::Date   => $this->castDate($value),
            SettingType::Enum   => $this->castEnum($value),
        };
    }

    /**
     * Cast a value as the settings table stored it, reading it the way the
     * settings service did: a boolean is true only for `true`/`1`, and an
     * integer is the string's integer prefix.
     *
     * @throws InvalidArgumentException when the value cannot represent the type
     */
    public function castLegacy(?string $value): mixed
    {
        $value ??= '';

        return match ($this->type) {
            SettingType::Bool  => in_array($value, ['true', '1'], true),
            SettingType::Int   => (int) $value,
            SettingType::Float => $value === '' ? 0.0 : $this->castFloat($value),
            default            => $this->cast($value),
        };
    }

    private function castBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = is_string($value) ? strtolower(trim($value)) : $value;

        return match (true) {
            in_array($normalized, [1, '1', 'true', 'on', 'yes'], true)            => true,
            in_array($normalized, [0, '0', 'false', 'off', 'no', '', null], true) => false,
            default                                                               => throw $this->invalid($value),
        };
    }

    private function castInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if ((is_float($value) || (is_string($value) && is_numeric(trim($value)))) && (float) $value == (int) $value) {
            return (int) $value;
        }

        throw $this->invalid($value);
    }

    private function castFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            return (float) $value;
        }

        throw $this->invalid($value);
    }

    private function castString(mixed $value): ?string
    {
        if ($value === null) {
            return $this->nullable ? null : '';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw $this->invalid($value);
    }

    private function castDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value)) {
            try {
                return CarbonImmutable::parse($value);
            } catch (Throwable) {
                throw $this->invalid($value);
            }
        }

        throw $this->invalid($value);
    }

    private function castEnum(mixed $value): BackedEnum
    {
        /** @var class-string<BackedEnum> $enum */
        $enum = $this->enum;

        if ($value instanceof $enum) {
            return $value;
        }

        if (is_int($value) || is_string($value)) {
            $case = $enum::tryFrom($value);

            if ($case !== null) {
                return $case;
            }
        }

        throw $this->invalid($value);
    }

    private function invalid(mixed $value): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'Setting "%s" cannot be set to %s.',
            $this->key,
            is_scalar($value) ? var_export($value, true) : get_debug_type($value),
        ));
    }

    /**
     * The translation for this setting's `$part`, or null when none exists.
     */
    private function translate(string $part): ?string
    {
        if ($this->translationPrefix === null) {
            return null;
        }

        $key = $this->translationPrefix.'.'.$part;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : null;
    }
}
