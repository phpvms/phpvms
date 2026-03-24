<?php

use App\Repositories\KvpRepository;
use App\Support\ICAO;
use App\Support\Units\Distance;
use App\Support\Units\Time;
use App\Support\Utils;
use Carbon\Carbon;

test('dates', function () {
    $carbon = new Carbon('2018-04-28T12:55:40Z');
    expect($carbon)->not->toBeNull();
});

test('unit rounding', function () {
    updateSetting('units.distance', 'km');

    $alt = new Distance(1065.3456, 'nmi');

    $km = $alt->toUnit('km');
    expect($km)->toEqualWithDelta(1973.0200512, 0.1);

    $km = $alt->toUnit('km', 2);
    expect($km)->toEqualWithDelta(1973.02, 0.1);

    $km = $alt->toUnit('km', 0);
    expect($km)->toEqualWithDelta(1973, 0.1);

    /*
     * Test local conversions
     */
    $km = $alt->local();
    expect($km)->toEqualWithDelta(1973.0200512, 0.1);

    $km = $alt->local(0);
    expect($km)->toEqualWithDelta(1973, 0.1);

    $km = $alt->local(2);
    expect($km)->toEqualWithDelta(1973.02, 0.1);

    /*
     * Internal units, shouldn't do a conversion
     */
    $int = $alt->internal();
    expect($int)->toEqualWithDelta(1065.3456, 0.1);

    $int = $alt->internal(2);
    expect($int)->toEqualWithDelta(1065.35, 0.1);

    $int = $alt->internal(0);
    expect($int)->toEqualWithDelta(1065, 0.1);
});

test('kvp', function () {
    /** @var KvpRepository $kvpRepo */
    $kvpRepo = app(KvpRepository::class);
    $kvpRepo->save('testkey', 'some value');
    expect($kvpRepo->get('testkey'))->toEqual('some value')
        ->and($kvpRepo->get('unknownkey', 'default value'))->toEqual('default value');

    // try saving an integer
    $kvpRepo->save('intval', 1);
    expect($kvpRepo->get('intval'))->toEqual(1);
});

test('seconds to time parts', function () {
    $t = Time::secondsToTimeParts(3600);
    expect($t)->toEqual(['h' => 1, 'm' => 0, 's' => 0]);

    $t = Time::secondsToTimeParts(3720);
    expect($t)->toEqual(['h' => 1, 'm' => 2, 's' => 0]);

    $t = Time::secondsToTimeParts(3722);
    expect($t)->toEqual(['h' => 1, 'm' => 2, 's' => 2]);

    $t = Time::secondsToTimeParts(60);
    expect($t)->toEqual(['h' => 0, 'm' => 1, 's' => 0]);

    $t = Time::secondsToTimeParts(62);
    expect($t)->toEqual(['h' => 0, 'm' => 1, 's' => 2]);
});

test('seconds to time', function () {
    $t = Time::secondsToTimeString(3600);
    expect($t)->toEqual('1h 0m');

    $t = Time::secondsToTimeString(3720);
    expect($t)->toEqual('1h 2m');

    $t = Time::secondsToTimeString(3722);
    expect($t)->toEqual('1h 2m');

    $t = Time::secondsToTimeString(3722, true);
    expect($t)->toEqual('1h 2m 2s');
});

test('minutes to time', function () {
    $t = Time::minutesToTimeParts(65);
    expect($t)->toEqual(['h' => 1, 'm' => 5]);

    $t = Time::minutesToTimeString(65);
    expect($t)->toEqual('1h 5m');

    $t = Time::minutesToTimeString(43200);
    expect($t)->toEqual('720h 0m');
});

test('api key', function () {
    $api_key = Utils::generateApiKey();
    expect($api_key)->not->toBeNull();
});

test('hex code', function () {
    $hex_code = ICAO::createHexCode();
    expect($hex_code)->not->toBeNull();
});

test('get domain', function () {
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
        expect(Utils::getRootDomain($case))->toEqual('phpvms.net');
    }

    expect(Utils::getRootDomain('http://phpvms.co.uk'))->toEqual('phpvms.co.uk')
        ->and(Utils::getRootDomain('http://www.phpvms.co.uk'))->toEqual('phpvms.co.uk')
        ->and(Utils::getRootDomain('http://127.0.0.1'))->toEqual('127.0.0.1')
        ->and(Utils::getRootDomain('http://localhost'))->toEqual('localhost');
});
