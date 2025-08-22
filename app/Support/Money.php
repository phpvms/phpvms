<?php

namespace App\Support;

use Akaunting\Money\Currency;
use Akaunting\Money\Money as MoneyBase;
use InvalidArgumentException;
use OutOfBoundsException;
use UnexpectedValueException;

/**
 * Compositional wrapper to MoneyPHP with some helpers
 */
class Money
{
    public MoneyBase $money;

    public $subunit_amount;

    public static $iso_currencies;

    public static $subunit_multiplier;

    /**
     * Create a new Money instance, passing in the amount in the subunit, e.,g, $5, you pass in 500)
     *
     * @param  mixed     $amount The amount, in pennies
     * @return MoneyBase
     *
     * @throws UnexpectedValueException
     */
    public static function create($amount)
    {
        return new MoneyBase($amount, static::currency());
    }

    /**
     * Create from a whole amount (e.g, dollars and cents - 50.05)
     *
     * @param  mixed $amount The amount in dollar
     * @return Money
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public static function createFromAmount($amount)
    {
        return new self(
            static::convertToSubunit($amount)
        );
    }

    /**
     * Convert a whole unit into it's subunit, e,g: dollar to cents
     *
     * @param  mixed $amount
     * @return int
     */
    public static function convertToSubunit($amount)
    {
        $currency = setting('units.currency', 'USD');

        return (int) ($amount * config('money.currencies.'.$currency.'.subunit'));
    }

    /**
     * Create a new currency object using the currency setting
     * Fall back to USD if it's not valid
     *
     * @return Currency
     *
     * @throws OutOfBoundsException
     */
    public static function currency()
    {
        try {
            return new Currency(setting('units.currency', 'USD'));
        } catch (OutOfBoundsException $e) {
            return new Currency('USD');
        }
    }

    /**
     * Money constructor.
     *
     * @param mixed $amount
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function __construct($amount)
    {
        $this->money = static::create($amount);
    }

    /**
     * Return the amount of currency in smallest denomination
     */
    public function getAmount(): float|int
    {
        return $this->money->getAmount();
    }

    /**
     * Alias of getAmount()
     */
    public function toAmount()
    {
        return $this->getAmount();
    }

    /**
     * Returns the value in whole amounts, e.g: 100.00
     * instead of returning in the smallest denomination
     *
     * @return float
     */
    public function getValue()
    {
        return $this->money->getValue();
    }

    /**
     * Alias of getValue()
     */
    public function toValue()
    {
        return $this->getValue();
    }

    /**
     * @return MoneyBase
     */
    public function getInstance()
    {
        return $this->money;
    }

    /**
     * @return int
     */
    public function getPrecision()
    {
        return $this->money->getCurrency()->getPrecision();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->money->format();
    }

    /**
     * Add an amount
     *
     * @param  mixed $amount
     * @return Money
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function add($amount)
    {
        if (!($amount instanceof self)) {
            $amount = static::createFromAmount($amount);
        }

        $this->money = $this->money->add($amount->money);

        return $this;
    }

    /**
     * @param  mixed $percent
     * @return $this
     *
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     */
    public function addPercent($percent)
    {
        if (!is_numeric($percent)) {
            $percent = (float) $percent;
        }

        $amount = $this->money->multiply($percent / 100);
        $this->money = $this->money->add($amount);

        return $this;
    }

    /**
     * Subtract an amount
     *
     *
     * @return Money
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function subtract($amount)
    {
        if (!($amount instanceof self)) {
            $amount = static::createFromAmount($amount);
        }

        $this->money = $this->money->subtract($amount->money);

        return $this;
    }

    /**
     * Multiply by an amount
     *
     *
     * @return Money
     *
     * @throws UnexpectedValueException
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     */
    public function multiply(int|float $amount)
    {
        $this->money = $this->money->multiply($amount);

        return $this;
    }

    /**
     * Divide by an amount
     *
     *
     * @return Money
     *
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     */
    public function divide($amount)
    {
        $this->money = $this->money->divide($amount, PHP_ROUND_HALF_EVEN);

        return $this;
    }

    /**
     * @return bool
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function equals($money)
    {
        if ($money instanceof self) {
            return $this->money->equals($money->money);
        }

        if ($money instanceof MoneyBase) {
            return $this->money->equals($money);
        }

        $money = static::convertToSubunit($money);

        return $this->money->equals(static::create($money));
    }
}
