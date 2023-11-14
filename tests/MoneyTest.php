<?php

namespace Tests;

use App\Support\Money;

class MoneyTest extends TestCase
{
    /**
     * Test adding/subtracting a percentage
     */
    public function testMoney()
    {
        $money = Money::create(100);
        $this->assertEquals('$1.00', $money->render());

        $moneyDollar = Money::createFromAmount(1.00);
        $this->assertEquals($money->getAmount(), $moneyDollar->getAmount());
    }
}
