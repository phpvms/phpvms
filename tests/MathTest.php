<?php

namespace Tests;

use App\Support\Math;
use App\Support\Units\Distance;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;

final class MathTest extends TestCase
{
    /**
     * Test adding/subtracting a percentage
     */
    public function test_add_percent(): void
    {
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
            $this->assertEqualsWithDelta($test['expected'], $test['fn'], 0.1);
        }
    }

    /**
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    public function test_distance_measurement(): void
    {
        $dist = new Distance(1, 'mi');
        $this->assertEqualsWithDelta(1609.34, $dist['m'], 0.1);
        $this->assertEqualsWithDelta(1.61, $dist['km'], 0.1);
    }
}
