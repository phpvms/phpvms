<?php

namespace Tests;

use App\Repositories\SettingRepository;
use App\Services\AirportService;
use App\Support\Metar;

/**
 * Test the parsing/support class of the metar.
 */
final class MetarTest extends TestCase
{
    private SettingRepository $settingsRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settingsRepo = app(SettingRepository::class);
    }

    /**
     * Make sure a blank metar doesn't give problems.
     */
    public function test_blank_metar(): void
    {
        $metar = '';
        $parsed = Metar::parse($metar);
        $this->assertEquals('', $parsed['raw']);
    }

    /**
     * Test adding/subtracting a percentage.
     */
    public function test_metar1(): void
    {
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
        $this->assertEquals('KJFK', $parsed['station']);
        $this->assertEquals(4, $parsed['observed_day']);
        $this->assertEquals('21:51 UTC', $parsed['observed_time']);
        $this->assertEquals(26, $parsed['wind_speed']['knots']);
        $this->assertEquals(39, $parsed['wind_gust_speed']['knots']);
        $this->assertEquals(280, $parsed['wind_direction']);
        $this->assertEquals('W', $parsed['wind_direction_label']);
        $this->assertFalse($parsed['wind_direction_varies']);
        $this->assertEquals(16093.44, $parsed['visibility']['m']);
        $this->assertEquals('Dry', $parsed['present_weather_report']);

        $this->assertCount(4, $parsed['clouds']);
        $this->assertEquals(
            'Few at 1676 meters; scattered at 2896 meters; broken sky at 3353 meters; broken sky at 7010 meters',
            $parsed['clouds_report']
        );
        $this->assertEquals(1676.4, $parsed['cloud_height']['m']);
        $this->assertFalse($parsed['cavok']);

        $this->assertEquals(12, $parsed['temperature']['c']);
        $this->assertEquals(53.6, $parsed['temperature']['f']);

        $this->assertEquals(-4, $parsed['dew_point']['c']);
        $this->assertEquals(24.8, $parsed['dew_point']['f']);

        $this->assertEquals(33, $parsed['humidity']);
        $this->assertEquals(29.58, $parsed['barometer']['inHg']);

        $this->assertEquals('AO2 PK WND 27045/2128 PRESRR SLP018 T01221044', $parsed['remarks']);
    }

    public function test_metar2(): void
    {
        $metar = 'EGLL 261250Z AUTO 17014KT 8000 -RA BKN010/// '
                .'BKN016/// OVC040/// //////TCU 13/12 Q1008 TEMPO 4000 RA';

        $parsed = Metar::parse($metar);

        $this->assertCount(4, $parsed['clouds']);
        $this->assertEquals(1000, $parsed['clouds'][0]['height']['ft']);
        $this->assertEquals(1600, $parsed['clouds'][1]['height']['ft']);
        $this->assertEquals(4000, $parsed['clouds'][2]['height']['ft']);
        $this->assertNull($parsed['clouds'][3]['height']);
    }

    public function test_metar3(): void
    {
        $metar = 'LEBL 310337Z 24006G18KT 210V320 1000 '
                .'R25R/P2000 R07L/1900N R07R/1700D R25L/1900N '
                .'+TSRA SCT006 BKN015 SCT030CB 22/21 Q1018 NOSIG';

        $parsed = Metar::parse($metar);
    }

    public function test_metar_trends(): void
    {
        $metar =
            'KJFK 070151Z 20005KT 10SM BKN100 08/07 A2970 RMK AO2 SLP056 T00780067';

        /**
         * John F.Kennedy International, New York, NY (KJFK). Apr 7, 0151Z. Wind from 200° at 5 knots,
         * 10 statute miles visibility, Ceiling is Broken at 10,000 feet, Temperature 8°C, Dewpoint 7°C,
         * Altimeter is 29.70. Remarks: automated station with precipitation discriminator sea level
         * pressure 1005.6 hectopascals hourly temp 7.8°C dewpoint 6.7°C.
         */
        $parsed = Metar::parse($metar);
    }

    public function test_metar_trends2(): void
    {
        $metar = 'KAUS 092135Z 26018G25KT 8SM -TSRA BR SCT045CB BKN060 OVC080 30/21 A2992 RMK FQT LTGICCCCG OHD-W MOVG E  RAB25 TSB32 CB ALQDS  SLP132 P0035 T03020210 =';
        $parsed = Metar::parse($metar);

        $this->assertEquals('VFR', $parsed['category']);
        $this->assertEquals(18, $parsed['wind_speed']['knots']);
        $this->assertEquals(8, $parsed['visibility']['mi']);
        $this->assertEquals(
            'Scattered at 4500 feet, cumulonimbus; broken sky at 6000 feet; overcast sky at 8000 feet',
            $parsed['clouds_report_ft']
        );
    }

    public function test_metar_trends3(): void
    {
        $metar = 'EHAM 041455Z 13012KT 9999 FEW034CB BKN040 05/01 Q1007 TEMPO 14017G28K 4000 SHRA =';
        $metar = Metar::parse($metar);

        $this->assertEquals('VFR', $metar['category']);
    }

    public function test_metar4_clouds(): void
    {
        $metar = 'KAUS 171153Z 18006KT 9SM FEW015 FEW250 26/24 A3003 RMK AO2 SLP156 T02560244 10267 20239 $';
        $metar = Metar::parse($metar);

        $this->assertCount(2, $metar['clouds']);
        $this->assertEquals('Few at 457 meters; few at 7620 meters', $metar['clouds_report']);
        $this->assertEquals('Few at 1500 feet; few at 25000 feet', $metar['clouds_report_ft']);
    }

    /**
     * https://github.com/nabeelio/phpvms/issues/1071.
     */
    public function test_metar_wind_speed_chill(): void
    {
        $metar = 'EKYT 091020Z /////KT CAVOK 02/M03 Q1019';
        $metar = Metar::parse($metar);

        $this->assertEquals('VFR', $metar['category']);
        $this->assertNull($metar['wind_speed']);
        $this->assertEquals(6.21, $metar['visibility']['mi']);
    }

    /**
     * Visibility in KM not parsed.
     *
     * https://github.com/nabeelio/phpvms/issues/680
     */
    public function test_metar5(): void
    {
        $metar = 'NZOH 031300Z 04004KT 38KM SCT075 BKN090 15/14 Q1002 RMK AUTO NZPM VATSIM USE ONL';
        $metar = Metar::parse($metar);

        $this->assertEquals(38, $metar['visibility']['km']);
        $this->assertEquals('38 km', $metar['visibility_report']);
    }

    public function test_lgkl(): void
    {
        $metar = 'LGKL 160320Z AUTO VRB02KT //// -RA ////// 07/04 Q1008 RE//';
        $metar = Metar::parse($metar);

        $this->assertEquals(2, $metar['wind_speed']['knots']);
        $this->assertEquals('Light rain', $metar['present_weather_report']);
    }

    public function test_lbbg(): void
    {
        $metar = 'LBBG 041600Z 12003MPS 310V290 1400 R04/1000D R22/P1500U +SN BKN022 OVC050 M04/M07 Q1020 NOSIG 9949//91=';
        $metar = Metar::parse($metar);

        $this->assertEquals('1000m and decreasing', $metar['runways_visual_range'][0]['report']);
    }

    public function test_http_call_success(): void
    {
        $this->mockPlainTextResponse('aviationweather/kjfk.txt');

        /** @var AirportService $airportSvc */
        $airportSvc = app(AirportService::class);

        $this->assertInstanceOf(Metar::class, $airportSvc->getMetar('kjfk'));
    }

    /**
     * TEMPO and trend causing issue with values being overwritten
     * https://github.com/nabeelio/phpvms/issues/861.
     */
    public function test_lfrs_call(): void
    {
        $this->mockPlainTextResponse('aviationweather/lfrs.txt');

        /** @var AirportService $airportSvc */
        $airportSvc = app(AirportService::class);

        $metar = $airportSvc->getMetar('lfrs');
        $this->assertInstanceOf(Metar::class, $metar);
        $this->assertTrue($metar['cavok']);
    }

    public function test_http_call_success_full_response(): void
    {
        $this->mockPlainTextResponse('aviationweather/kphx.txt');
        $airportSvc = app(AirportService::class);

        $this->assertInstanceOf(Metar::class, $airportSvc->getMetar('kphx'));
    }

    public function test_http_call_empty(): void
    {
        $this->mockPlainTextResponse('aviationweather/empty.txt');
        $airportSvc = app(AirportService::class);

        $this->assertNull($airportSvc->getMetar('idk'));
    }
}
