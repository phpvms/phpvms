<?php

declare(strict_types=1);

use App\Support\Money;

test('money', function (): void {
    $money = Money::create(100);
    expect($money->render())->toEqual('$1.00');

    $moneyDollar = Money::createFromAmount(1.00);
    expect($moneyDollar->getAmount())->toEqual($money->getAmount());
});
