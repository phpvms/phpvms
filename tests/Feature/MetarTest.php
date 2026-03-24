<?php

use App\Services\AirportService;
use App\Support\Metar;

test('blank metar', function () {
    $metar = '';
    $parsed = Metar::parse($metar);
    expect($parsed['raw'])->toEqual('');
});

test('metar1', function () {
    $metar =
        'KJFK 042151Z 28026G39KT 10SM FEW055 SCT095 BKN110 BKN230 12/M04 A2958 RMK AO2 PK WND 27045/2128 PRESRR SLP018 T01221044';

    // $m = new Metar($metar);
    // $parsed = $m->result;
    $parsed = Metar::parse($metar);

    /*
       Conditions  VFR visibility 10NM
       Barometer   1001.58 Hg / 29.58 MB
       Clouds      FEW @ 5500 ft
                   SCT @ 9500 ft
                   BKN @ 11000 ft
                   BKN @ 23000 ft
       Wind        26 kts @ 280° gusts to 39
    */
    expect($parsed['station'])->toEqual('KJFK')
        ->and($parsed['observed_day'])->toEqual(4)
        ->and($parsed['observed_time'])->toEqual('21:51 UTC')
        ->and($parsed['wind_speed']['knots'])->toEqual(26)
        ->and($parsed['wind_gust_speed']['knots'])->toEqual(39)
        ->and($parsed['wind_direction'])->toEqual(280)
        ->and($parsed['wind_direction_label'])->toEqual('W')
        ->and($parsed['wind_direction_varies'])->toBeFalse()
        ->and($parsed['visibility']['m'])->toEqual(16093.44)
        ->and($parsed['present_weather_report'])->toEqual('Dry')
        ->and($parsed['clouds'])->toHaveCount(4)
        ->and($parsed['clouds_report'])->toEqual('Few at 1676 meters; scattered at 2896 meters; broken sky at 3353 meters; broken sky at 7010 meters')
        ->and($parsed['cloud_height']['m'])->toEqual(1676.4)
        ->and($parsed['cavok'])->toBeFalse()
        ->and($parsed['temperature']['c'])->toEqual(12)
        ->and($parsed['temperature']['f'])->toEqual(53.6)
        ->and($parsed['dew_point']['c'])->toEqual(-4)
        ->and($parsed['dew_point']['f'])->toEqual(24.8)
        ->and($parsed['humidity'])->toEqual(33)
        ->and($parsed['barometer']['inHg'])->toEqual(29.58)
        ->and($parsed['remarks'])->toEqual('AO2 PK WND 27045/2128 PRESRR SLP018 T01221044');

});

test('metar2', function () {
    $metar = 'EGLL 261250Z AUTO 17014KT 8000 -RA BKN010/// '
            .'BKN016/// OVC040/// //////TCU 13/12 Q1008 TEMPO 4000 RA';

    $parsed = Metar::parse($metar);

    expect($parsed['clouds'])->toHaveCount(4)
        ->and($parsed['clouds'][0]['height']['ft'])->toEqual(1000)
        ->and($parsed['clouds'][1]['height']['ft'])->toEqual(1600)
        ->and($parsed['clouds'][2]['height']['ft'])->toEqual(4000)
        ->and($parsed['clouds'][3]['height'])->toBeNull();
});

test('metar3', function () {
    $metar = 'LEBL 310337Z 24006G18KT 210V320 1000 '
        .'R25R/P2000 R07L/1900N R07R/1700D R25L/1900N '
        .'+TSRA SCT006 BKN015 SCT030CB 22/21 Q1018 NOSIG';

    $parsed = Metar::parse($metar);

    expect($parsed['station'])->toEqual('LEBL')
        ->and($parsed['wind_direction'])->toEqual(240)
        ->and($parsed['wind_speed']['knots'])->toEqual(6)
        ->and($parsed['wind_gust_speed']['knots'])->toEqual(18)
        // Visibility 1000 meters
        ->and($parsed['visibility']['m'])->toEqual(1000)
        // Weather: Heavy Thunderstorm with Rain
        ->and($parsed['present_weather_report'])->toEqual('Strong thunderstorms rain')
        // RVR Assertions
        ->and($parsed['runways_visual_range'])->toHaveCount(4)
        ->and($parsed['runways_visual_range'][0]['runway'])->toEqual('25R')
        ->and($parsed['runways_visual_range'][0]['report'])->toEqual('2000m')
        ->and($parsed['runways_visual_range'][2]['runway'])->toEqual('07R')
        ->and($parsed['runways_visual_range'][2]['report'])->toEqual('1700m and decreasing')
        // Clouds including Cumulonimbus
        ->and($parsed['clouds'])->toHaveCount(3)
        ->and($parsed['clouds'][2]['type'])->toEqual('CB')
        ->and($parsed['barometer']['hPa'])->toEqual(1018);
});

test('metar trends', function () {
    $metar =
        'KJFK 070151Z 20005KT 10SM BKN100 08/07 A2970 RMK AO2 SLP056 T00780067';

    $parsed = Metar::parse($metar);

    expect($parsed['station'])->toEqual('KJFK')
        ->and($parsed['visibility']['mi'])->toEqual(10)
        ->and($parsed['visibility']['m'])->toEqual(16093.44)
        ->and($parsed['clouds'][0]['report'])->toEqual('Broken sky at 3048 meters')
        ->and($parsed['temperature']['c'])->toEqual(8)
        ->and($parsed['dew_point']['c'])->toEqual(7)
        ->and($parsed['barometer']['inHg'])->toEqual(29.70)
        // Remark check
        ->and($parsed['remarks'])->toContain('AO2')
        ->and($parsed['remarks'])->toContain('SLP056');
});

test('metar trends2', function () {
    $metar = 'KAUS 092135Z 26018G25KT 8SM -TSRA BR SCT045CB BKN060 OVC080 30/21 A2992 RMK FQT LTGICCCCG OHD-W MOVG E  RAB25 TSB32 CB ALQDS  SLP132 P0035 T03020210 =';
    $parsed = Metar::parse($metar);

    expect($parsed['category'])->toEqual('VFR')
        ->and($parsed['wind_speed']['knots'])->toEqual(18)
        ->and($parsed['visibility']['mi'])->toEqual(8)
        ->and($parsed['clouds_report_ft'])->toEqual('Scattered at 4500 feet, cumulonimbus; broken sky at 6000 feet; overcast sky at 8000 feet');
});

test('metar trends3', function () {
    $metar = 'EHAM 041455Z 13012KT 9999 FEW034CB BKN040 05/01 Q1007 TEMPO 14017G28K 4000 SHRA =';
    $metar = Metar::parse($metar);

    expect($metar['category'])->toEqual('VFR');
});

test('metar4 clouds', function () {
    $metar = 'KAUS 171153Z 18006KT 9SM FEW015 FEW250 26/24 A3003 RMK AO2 SLP156 T02560244 10267 20239 $';
    $metar = Metar::parse($metar);

    expect($metar['clouds'])->toHaveCount(2)
        ->and($metar['clouds_report'])->toEqual('Few at 457 meters; few at 7620 meters')
        ->and($metar['clouds_report_ft'])->toEqual('Few at 1500 feet; few at 25000 feet');
});

test('metar wind speed chill', function () {
    $metar = 'EKYT 091020Z /////KT CAVOK 02/M03 Q1019';
    $metar = Metar::parse($metar);

    expect($metar['category'])->toEqual('VFR')
        ->and($metar['wind_speed'])->toBeNull()
        ->and($metar['visibility']['mi'])->toEqual(6.21);
});

test('metar5', function () {
    $metar = 'NZOH 031300Z 04004KT 38KM SCT075 BKN090 15/14 Q1002 RMK AUTO NZPM VATSIM USE ONL';
    $metar = Metar::parse($metar);

    expect($metar['visibility']['km'])->toEqual(38)
        ->and($metar['visibility_report'])->toEqual('38 km');
});

test('lgkl', function () {
    $metar = 'LGKL 160320Z AUTO VRB02KT //// -RA ////// 07/04 Q1008 RE//';
    $metar = Metar::parse($metar);

    expect($metar['wind_speed']['knots'])->toEqual(2)
        ->and($metar['present_weather_report'])->toEqual('Light rain');
});

test('lbbg', function () {
    $metar = 'LBBG 041600Z 12003MPS 310V290 1400 R04/1000D R22/P1500U +SN BKN022 OVC050 M04/M07 Q1020 NOSIG 9949//91=';
    $metar = Metar::parse($metar);

    expect($metar['runways_visual_range'][0]['report'])->toEqual('1000m and decreasing');
});

test('http call success', function () {
    mockGuzzleResponse('aviationweather/kjfk.txt', 'text/plain');

    /** @var AirportService $airportSvc */
    $airportSvc = app(AirportService::class);

    expect($airportSvc->getMetar('kjfk'))->toBeInstanceOf(Metar::class);
});

test('lfrs call', function () {
    mockGuzzleResponse('aviationweather/lfrs.txt', 'text/plain');

    /** @var AirportService $airportSvc */
    $airportSvc = app(AirportService::class);

    $metar = $airportSvc->getMetar('lfrs');
    expect($metar)->toBeInstanceOf(Metar::class);
    expect($metar['cavok'])->toBeTrue();
});

test('http call success full response', function () {
    mockGuzzleResponse('aviationweather/kphx.txt', 'text/plain');
    $airportSvc = app(AirportService::class);

    expect($airportSvc->getMetar('kphx'))->toBeInstanceOf(Metar::class);
});

test('http call empty', function () {
    mockGuzzleResponse('aviationweather/empty.txt', 'text/plain');
    $airportSvc = app(AirportService::class);

    expect($airportSvc->getMetar('idk'))->toBeNull();
});
