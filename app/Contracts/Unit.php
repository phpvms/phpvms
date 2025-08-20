<?php

namespace App\Contracts;

use ArrayAccess;
use Exception;

/**
 * Abstract unit wrapper
 */
abstract class Unit implements ArrayAccess
{
    /**
     * The localized unit the user wants it displayed in
     */
    public string $localUnit;

    /**
     * The unit that this value is stored in locally
     */
    public string $internalUnit;

    /**
     * All of the units of this class which are reported in an API response
     */
    public array $units;

    /**
     * Holds an instance of the PhpUnit type
     */
    protected mixed $instance;

    /**
     * Units that are included as part of the REST response
     */
    public array $responseUnits = [];

    abstract public function __construct(mixed $value, string $unit);

    /**
     * Factory method for creating a new unit type
     *
     *
     * @throws Exception
     */
    public static function make(mixed $value, string $unit): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return new static($value, $unit);
    }

    /**
     * Return the value in an internal format
     *
     * @param int|null $round Optional value to round to
     */
    public function internal(?int $round = null): ?float
    {
        return $this->toUnit($this->internalUnit, $round);
    }

    /**
     * Return the value in the localized format
     *
     * @param int|null $round Optional value to round to
     */
    public function local(?int $round = null): ?float
    {
        return $this->toUnit($this->localUnit, $round);
    }

    /**
     * Just call toUnit() on the PhpUnitOfMeasure instance
     *
     * @param int|null $round Optional value to round to
     */
    public function toUnit(string $unit, ?int $round = null): ?float
    {
        $val = $this->instance->toUnit($unit);
        if ($round === null) {
            return $val;
        }

        return round($val, $round);
    }

    /**
     * Return all of the units that get sent back in a response
     */
    public function getResponseUnits(): array
    {
        $response = [];
        foreach ($this->responseUnits as $unit) {
            $response[$unit] = round($this->instance->toUnit($unit), 2);
        }

        return $response;
    }

    /**
     * Implements ArrayAccess
     */
    public function offsetExists($offset): bool
    {
        return $this->offsetGet($offset) !== null;
    }

    /**
     * Implements ArrayAccess
     */
    public function offsetGet($unit): ?float
    {
        $value = $this->instance->toUnit($unit);
        if (!$value) {
            return null;
        }

        return round($value, 2);
    }

    /**
     * Implements ArrayAccess
     */
    public function offsetSet($offset, $value): void
    {
        // $this->units[$offset] = $value;
    }

    /**
     * Implements ArrayAccess
     */
    public function offsetUnset($offset): void
    {
        // $this->units[$offset] = null;
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return (string) $this->offsetGet($this->localUnit);
    }
}
