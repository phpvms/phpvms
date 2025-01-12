<?php

namespace App\Contracts;

/**
 * Borrowed some ideas from myclabs/php-enum after this was created.
 */
abstract class Enum
{
    protected static array $cache = [];

    protected static array $codes = [];

    protected static array $labels = [];

    protected int $value;

    /**
     * Create an instance of this Enum.
     */
    public function __construct($val)
    {
        $this->value = $val;
    }

    /**
     * Return the value that's been set if this is an instance.
     */
    final public function getValue(): ?int
    {
        return $this->value;
    }

    /**
     * Return the label, try to return the translated version as well.
     */
    final public static function label($value): string
    {
        if (isset(static::$labels[$value])) {
            $val = static::$labels[$value];
            if (strpos($val, '.') !== false) {
                return trans($val);
            }

            return $val;
        }

        return $value;
    }

    /**
     * Return all of the (translated) labels.
     */
    final public static function labels(): array
    {
        $labels = [];
        foreach (static::$labels as $key => $label) {
            $labels[$key] = trans($label);
        }

        return $labels;
    }

    /**
     * Get the numeric value from a string code.
     *
     *
     * @return mixed|null
     */
    public static function getFromCode($code)
    {
        return array_search($code, static::$codes, true);
    }

    /**
     * Convert the integer value into one of the codes.
     *
     *
     * @return false|int|string
     */
    public static function convertToCode($value): bool|int|string|null
    {
        $value = (int) $value;
        if (!array_key_exists($value, static::$codes)) {
            return null;
        }

        return static::$codes[$value];
    }

    /**
     * Select box entry items.
     *
     * @param bool $add_blank
     */
    public static function select($add_blank = false): array
    {
        $labels = [];
        if ($add_blank) {
            $labels[] = '';
        }

        foreach (static::$labels as $key => $label) {
            $labels[$key] = trans($label);
        }

        return $labels;
    }

    /**
     * Returns all possible values as an array.
     *
     * @return array Constant name in key, constant value in value
     *
     * @throws \ReflectionException
     */
    public static function toArray(): array
    {
        $class = static::class;
        if (!array_key_exists($class, static::$cache)) {
            $reflection = new \ReflectionClass($class);
            static::$cache[$class] = $reflection->getConstants();
        }

        return static::$cache[$class];
    }

    final public function equals(self $enum): bool
    {
        return $this->getValue() === $enum->getValue() && static::class === \get_class($enum);
    }

    /**
     * Returns a value when called statically like so: MyEnum::SOME_VALUE() given SOME_VALUE is a
     * class constant.
     *
     * @param  string $name
     * @param  array  $arguments
     * @return static
     *
     * @throws \BadMethodCallException
     * @throws \ReflectionException
     */
    public static function __callStatic($name, $arguments)
    {
        $array = static::toArray();
        if (isset($array[$name])) {
            return new static($array[$name]);
        }

        throw new \BadMethodCallException(
            "No static method or enum constant '$name' in class ".static::class
        );
    }
}
