<?php

namespace App\Support;

use Akaunting\Money\Currency;
use Akaunting\Money\Money as MoneyBase;

/**
 * Compositional wrapper to MoneyPHP with some helpers
 */
class Money implements \Stringable
{
    public MoneyBase $money;

    public $subunit_amount;

    public static $iso_currencies;

    public static $subunit_multiplier;

    /**
     * Create a new Money instance, passing in the amount in the subunit, e.,g, $5, you pass in 500)
     *
     * @param mixed $amount The amount, in pennies
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public static function create($amount): \Akaunting\Money\Money
    {
        return new MoneyBase($amount, static::currency());
    }

    /**
     * Create from a whole amount (e.g, dollars and cents - 50.05)
     *
     * @param mixed $amount The amount in dollar
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public static function createFromAmount($amount): self
    {
        return new self(
            static::convertToSubunit($amount)
        );
    }

    /**
     * Convert a whole unit into it's subunit, e,g: dollar to cents
     *
     * @param mixed $amount
     */
    public static function convertToSubunit($amount): int
    {
        $currency = setting('units.currency', 'USD');

        return (int) ($amount * config('money.currencies.'.$currency.'.subunit'));
    }

    /**
     * Create a new currency object using the currency setting
     * Fall back to USD if it's not valid
     *
     *
     * @throws \OutOfBoundsException
     */
    public static function currency(): \Akaunting\Money\Currency
    {
        try {
            return new Currency(setting('units.currency', 'USD'));
        } catch (\OutOfBoundsException) {
            return new Currency('USD');
        }
    }

    /**
     * Money constructor.
     *
     * @param mixed $amount
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function __construct($amount)
    {
        $this->money = static::create($amount);
    }

    /**
     * Return the amount of currency in smallest denomination
     */
    public function getAmount(): int|float
    {
        return $this->money->getAmount();
    }

    /**
     * Alias of getAmount()
     */
    public function toAmount(): int|float
    {
        return $this->getAmount();
    }

    /**
     * Returns the value in whole amounts, e.g: 100.00
     * instead of returning in the smallest denomination
     */
    public function getValue(): float
    {
        return $this->money->getValue();
    }

    /**
     * Alias of getValue()
     */
    public function toValue(): float
    {
        return $this->getValue();
    }

    public function getInstance(): \Akaunting\Money\Money
    {
        return $this->money;
    }

    public function getPrecision(): int
    {
        return $this->money->getCurrency()->getPrecision();
    }

    public function __toString(): string
    {
        return $this->money->format();
    }

    /**
     * Add an amount
     *
     * @param mixed $amount
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function add($amount): static
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
     * @throws \OutOfBoundsException
     * @throws \InvalidArgumentException
     */
    public function addPercent($percent): static
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
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function subtract($amount): static
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
     *
     * @throws \UnexpectedValueException
     * @throws \OutOfBoundsException
     * @throws \InvalidArgumentException
     */
    public function multiply($amount): static
    {
        if (!($amount instanceof self)) {
            $amount = static::createFromAmount($amount);
        }

        $this->money = $this->money->multiply($amount->money);

        return $this;
    }

    /**
     * Divide by an amount
     *
     *
     *
     * @throws \OutOfBoundsException
     * @throws \InvalidArgumentException
     */
    public function divide(int|float $amount): static
    {
        $this->money = $this->money->divide($amount, PHP_ROUND_HALF_EVEN);

        return $this;
    }

    /**
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function equals($money): bool
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
