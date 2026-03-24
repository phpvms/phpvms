<?php

use App\Support\Math;
use App\Support\Units\Distance;

test('add percent', function () {
    $tests = [
        ['expected' => 112, 'fn' => Math::getPercent(100, 112)],
        ['expected' => 112, 'fn' => Math::getPercent(100, '112')],
        ['expected' => 112, 'fn' => Math::getPercent(100, '112%')],
        ['expected' => 112, 'fn' => Math::getPercent(100, '112%')],
        ['expected' => 112, 'fn' => Math::getPercent('100 ', '112')],
        ['expected' => 112.5, 'fn' => Math::getPercent('100', '112.5')],
        ['expected' => 88, 'fn' => Math::getPercent('100', 88)],
        ['expected' => 88, 'fn' => Math::getPercent('100', '88')],
        ['expected' => 88, 'fn' => Math::getPercent('100', '88 %')],
        ['expected' => 88, 'fn' => Math::getPercent('100', '88%')],
    ];

    foreach ($tests as $test) {
        expect($test['fn'])->toEqualWithDelta($test['expected'], 0.1);
    }
});

test('distance measurement', function () {
    $dist = new Distance(1, 'mi');
    expect($dist['m'])->toEqualWithDelta(1609.34, 0.1)
        ->and($dist['km'])->toEqualWithDelta(1.61, 0.1);
});
