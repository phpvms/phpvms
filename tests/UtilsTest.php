<?php

namespace Tests;

use App\Repositories\KvpRepository;
use App\Support\ICAO;
use App\Support\Units\Distance;
use App\Support\Units\Time;
use App\Support\Utils;
use Carbon\Carbon;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;

final class UtilsTest extends TestCase
{
    public function testDates(): void
    {
        $carbon = new Carbon('2018-04-28T12:55:40Z');
        $this->assertNotNull($carbon);
    }

    /**
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    public function testUnitRounding(): void
    {
        $this->updateSetting('units.distance', 'km');

        $alt = new Distance(1065.3456, 'nmi');

        $km = $alt->toUnit('km');
        $this->assertEqualsWithDelta(1973.0200512, $km, 0.1);

        $km = $alt->toUnit('km', 2);
        $this->assertEqualsWithDelta(1973.02, $km, 0.1);

        $km = $alt->toUnit('km', 0);
        $this->assertEqualsWithDelta(1973, $km, 0.1);

        /*
         * Test local conversions
         */

        $km = $alt->local();
        $this->assertEqualsWithDelta(1973.0200512, $km, 0.1);

        $km = $alt->local(0);
        $this->assertEqualsWithDelta(1973, $km, 0.1);

        $km = $alt->local(2);
        $this->assertEqualsWithDelta(1973.02, $km, 0.1);

        /*
         * Internal units, shouldn't do a conversion
         */
        $int = $alt->internal();
        $this->assertEqualsWithDelta(1065.3456, $int, 0.1);

        $int = $alt->internal(2);
        $this->assertEqualsWithDelta(1065.35, $int, 0.1);

        $int = $alt->internal(0);
        $this->assertEqualsWithDelta(1065, $int, 0.1);
    }

    /**
     * Simple test for KVP
     */
    public function testKvp(): void
    {
        /** @var KvpRepository $kvpRepo */
        $kvpRepo = app(KvpRepository::class);
        $kvpRepo->save('testkey', 'some value');
        $this->assertEquals('some value', $kvpRepo->get('testkey'));

        // test that default value is working
        $this->assertEquals('default value', $kvpRepo->get('unknownkey', 'default value'));

        // try saving an integer
        $kvpRepo->save('intval', 1);
        $this->assertEquals(1, $kvpRepo->get('intval'));
    }

    /**
     * @throws \Exception
     */
    public function testSecondsToTimeParts(): void
    {
        $t = Time::secondsToTimeParts(3600);
        $this->assertEquals(['h' => 1, 'm' => 0, 's' => 0], $t);

        $t = Time::secondsToTimeParts(3720);
        $this->assertEquals(['h' => 1, 'm' => 2, 's' => 0], $t);

        $t = Time::secondsToTimeParts(3722);
        $this->assertEquals(['h' => 1, 'm' => 2, 's' => 2], $t);

        $t = Time::secondsToTimeParts(60);
        $this->assertEquals(['h' => 0, 'm' => 1, 's' => 0], $t);

        $t = Time::secondsToTimeParts(62);
        $this->assertEquals(['h' => 0, 'm' => 1, 's' => 2], $t);
    }

    /**
     * @throws \Exception
     */
    public function testSecondsToTime(): void
    {
        $t = Time::secondsToTimeString(3600);
        $this->assertEquals('1h 0m', $t);

        $t = Time::secondsToTimeString(3720);
        $this->assertEquals('1h 2m', $t);

        $t = Time::secondsToTimeString(3722);
        $this->assertEquals('1h 2m', $t);

        $t = Time::secondsToTimeString(3722, true);
        $this->assertEquals('1h 2m 2s', $t);
    }

    public function testMinutesToTime(): void
    {
        $t = Time::minutesToTimeParts(65);
        $this->assertEquals(['h' => 1, 'm' => 5], $t);

        $t = Time::minutesToTimeString(65);
        $this->assertEquals('1h 5m', $t);

        $t = Time::minutesToTimeString(43200);
        $this->assertEquals('720h 0m', $t);
    }

    public function testApiKey(): void
    {
        $api_key = Utils::generateApiKey();
        $this->assertNotNull($api_key);
    }

    /**
     * @throws \Exception
     */
    public function testHexCode(): void
    {
        $hex_code = ICAO::createHexCode();
        $this->assertNotNull($hex_code);
    }

    public function testGetDomain(): void
    {
        $tests = [
            'http://phpvms.net',
            'https://phpvms.net',
            'https://phpvms.net/',
            'phpvms.net',
            'https://phpvms.net/index.php',
            'https://demo.phpvms.net',
            'https://demo.phpvms.net/file/index.php',
        ];

        foreach ($tests as $case) {
            $this->assertEquals('phpvms.net', Utils::getRootDomain($case));
        }

        $this->assertEquals('phpvms.co.uk', Utils::getRootDomain('http://phpvms.co.uk'));
        $this->assertEquals('phpvms.co.uk', Utils::getRootDomain('http://www.phpvms.co.uk'));
        $this->assertEquals('127.0.0.1', Utils::getRootDomain('http://127.0.0.1'));
        $this->assertEquals('localhost', Utils::getRootDomain('http://localhost'));
    }
}
