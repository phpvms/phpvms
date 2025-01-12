<?php

namespace App\Support;

use App\Support\Units\Altitude;
use App\Support\Units\Distance;
use App\Support\Units\Pressure;
use App\Support\Units\Temperature;
use App\Support\Units\Velocity;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;

use function count;

/**
 * Class Metar.
 */

/*
    ===========================
    HSDN METAR/TAF Parser Class
    ===========================
    Version: 0.55.4b
    Based on GetWx script by Mark Woodward.
    (c) 2013-2015, Information Networks, Ltd. (http://www.hsdn.org/)
    (c) 2001-2006, Mark Woodward (http://woody.cowpi.com/phpscripts/)
        This script is a PHP library which allows to parse the METAR and TAF code,
    and convert it to an array of data parameters. These METAR or TAF can be given
    in the form of the ICAO code string (in this case, the script will receive data
    from the NOAA website) or in raw format (just METAR/TAF code string). METAR or
    TAF code parsed using the syntactic analysis and regular expressions. It solves
    the problem of parsing the data in the presence of any error in the code METAR
    or TAF. In addition to the return METAR parameters, the script also displays the
    interpreted (easy to understand) information of these parameters.
*/
class Metar implements \ArrayAccess
{
    /*
     * Array of decoded result, by default all parameters is null.
     */
    public $result = [
        'category'                 => null,
        'raw'                      => null,
        'taf'                      => null,
        'taf_flag'                 => null,
        'station'                  => null,
        'observed_date'            => null,
        'observed_day'             => null,
        'observed_time'            => null,
        'observed_age'             => null,
        'wind_speed'               => null,
        'wind_gust_speed'          => null,
        'wind_direction'           => null,
        'wind_direction_label'     => null,
        'wind_direction_varies'    => null,
        'varies_wind_min'          => null,
        'varies_wind_min_label'    => null,
        'varies_wind_max'          => null,
        'varies_wind_max_label'    => null,
        'visibility'               => null,
        'visibility_report'        => null,
        'visibility_min'           => null,
        'visibility_min_direction' => null,
        'runways_visual_range'     => null,
        'present_weather'          => null,
        'present_weather_report'   => null,
        'clouds'                   => null,
        'clouds_report'            => null,
        'clouds_report_ft'         => null,
        'cloud_height'             => null,
        'cavok'                    => null,
        'temperature'              => null,
        'dew_point'                => null,
        'humidity'                 => null,
        'heat_index'               => null,
        'wind_chill'               => null,
        'barometer'                => null,
        'barometer_in'             => null,
        'barometer_mb'             => null,
        'recent_weather'           => null,
        'recent_weather_report'    => null,
        'runways_report'           => null,
        'runways_snoclo'           => null,
        'wind_shear_all_runways'   => null,
        'wind_shear_runways'       => null,
        'forecast_temperature_min' => null,
        'forecast_temperature_max' => null,
        'trends'                   => null,
        'remarks'                  => null,
    ];

    /*
     * Methods used for parsing in the order of data
     */
    private static $method_names = [
        'taf',
        'station',
        'time',
        'station_type',
        'wind',
        'varies_wind',
        'visibility',
        'visibility_min',
        'runway_vr',
        'present_weather',
        'clouds',
        'temperature',
        'pressure',
        'recent_weather',
        'runways_report',
        'wind_shear',
        'forecast_temperature',
        'trends',
        'remarks',
    ];

    /*
     * Interpretation of weather conditions intensity codes.
     */
    private static $weather_intensity_codes = [
        ''   => 'moderate',
        '-'  => 'light',
        '+'  => 'strong',
        'VC' => 'in the vicinity',
    ];

    /*
     * Interpretation of weather conditions characteristics codes.
     */
    private static $weather_char_codes = [
        'MI' => 'shallow',
        'PR' => 'partial',
        'BC' => 'patches of',
        'DR' => 'low drifting',
        'BL' => 'blowing',
        'SH' => 'showers of',
        'TS' => 'thunderstorms',
        'FZ' => 'freezing',
    ];

    /*
     * Interpretation of weather conditions type codes.
     */
    private static $weather_type_codes = [
        'DZ' => 'drizzle',
        'RA' => 'rain',
        'SN' => 'snow',
        'SG' => 'snow grains',
        'IC' => 'ice crystals',
        'PE' => 'ice pellets',
        'GR' => 'hail',
        'GS' => 'small hail', // and/or snow pellets
        'UP' => 'unknown',
        'BR' => 'mist',
        'FG' => 'fog',
        'FU' => 'smoke',
        'VA' => 'volcanic ash',
        'DU' => 'widespread dust',
        'SA' => 'sand',
        'HZ' => 'haze',
        'PY' => 'spray',
        'PO' => 'well-developed dust/sand whirls',
        'SQ' => 'squalls',
        'FC' => 'funnel cloud, tornado, or waterspout',
        'SS' => 'sandstorm/duststorm',
    ];

    /*
     * Interpretation of cloud cover codes.
     */
    private static $cloud_codes = [
        'NSW'  => 'no significant weather are observed',
        'NSC'  => 'no significant clouds are observed',
        'NCD'  => 'nil cloud detected',
        'SKC'  => 'sky is clear',
        'CLR'  => 'clear skies',
        'NOBS' => 'no observation',
        //
        'FEW' => 'few',
        'SCT' => 'scattered',
        'BKN' => 'broken sky',
        'OVC' => 'overcast sky',
        //
        'VV' => 'vertical visibility',
    ];

    /*
     * Interpretation of cloud cover type codes.
     */
    private static $cloud_type_codes = [
        'CB'  => 'cumulonimbus',
        'TCU' => 'towering cumulus',
    ];

    /*
     * Interpretation of runway visual range tendency codes.
     */
    private static $rvr_tendency_codes = [
        'D' => 'decreasing',
        'U' => 'increasing',
        'N' => 'no tendency',
    ];

    /*
     * Interpretation of runway visual range prefix codes.
     */
    private static $rvr_prefix_codes = [
        'P' => 'more',
        'M' => 'less',
    ];

    /*
     * Interpretation of runway runway deposits codes.
     */
    private static $runway_deposits_codes = [
        '0' => 'clear and dry',
        '1' => 'damp',
        '2' => 'wet or water patches',
        '3' => 'rime or frost covered',
        '4' => 'dry snow',
        '5' => 'wet snow',
        '6' => 'slush',
        '7' => 'ice',
        '8' => 'compacted or rolled snow',
        '9' => 'frozen ruts or ridges',
        '/' => 'not reported',
    ];

    /*
     * Interpretation of runway runway deposits extent codes.
     */
    private static $runway_deposits_extent_codes = [
        '1' => 'from 10% or less',
        '2' => 'from 11% to 25%',
        '5' => 'from 26% to 50%',
        '9' => 'from 51% to 100%',
        '/' => null,
    ];

    /*
     * Interpretation of runway runway deposits depth codes.
     */
    private static $runway_deposits_depth_codes = [
        '00' => 'less than 1 mm',
        '92' => '10 cm',
        '93' => '15 cm',
        '94' => '20 cm',
        '95' => '25 cm',
        '96' => '30 cm',
        '97' => '35 cm',
        '98' => '40 cm or more',
        '99' => 'closed',
        '//' => null,
    ];

    /*
     * Interpretation of runway runway friction codes.
     */
    private static $runway_friction_codes = [
        '91' => 'poor',
        '92' => 'medium/poor',
        '93' => 'medium',
        '94' => 'medium/good',
        '95' => 'good',
        '99' => 'figures unreliable',
        '//' => null,
    ];

    /*
     * Trends time codes.
     */
    private static $trends_flag_codes = [
        'BECMG' => 'expected to arise soon',
        'TEMPO' => 'expected to arise temporarily',
        'INTER' => 'expected to arise intermittent',
        'PROV'  => 'provisional forecast',
        'CNL'   => 'cancelled forecast',
        'NIL'   => 'nil forecast',
    ];

    /*
     * Trends time codes.
     */
    private static $trends_time_codes = [
        'AT' => 'at',
        'FM' => 'from',
        'TL' => 'until',
    ];

    /*
     * Interpretation of compass degrees codes.
     */
    private static $direction_codes = [
        'N', 'NNE', 'NE', 'ENE',
        'E', 'ESE', 'SE', 'SSE',
        'S', 'SSW', 'SW', 'WSW',
        'W', 'WNW', 'NW', 'NNW',
    ];

    /*
     * Debug and parse errors information.
     */

    private $debug = [];

    private $debug_enabled;

    public $errors = [];

    /*
     * Other variables.
     */
    public $raw;

    private $raw_parts = [];

    private $method = 0;

    private $part = 0;

    /**
     * This method provides METAR and TAF information, you want to parse.
     *
     * Examples of raw METAR for test:
     * UMMS 231530Z 21002MPS 2100 BR OVC002 07/07 Q1008 R13/290062 NOSIG RMK QBB070
     * UWSS 231500Z 14007MPS 9999 -SHRA BR BKN033CB OVC066 03/M02 Q1019 R12/220395 NOSIG RMK QFE752
     * UWSS 241200Z 12003MPS 0300 R12/1000 DZ FG VV003CB 05/05 Q1015 R12/220395 NOSIG RMK QFE749
     * UATT 231530Z 18004MPS 130V200 CAVOK M03/M08 Q1033 R13/0///60 NOSIG RMK QFE755/1006
     * KEYW 231553Z 04008G16KT 10SM FEW060 28/22 A3002 RMK AO2 SLP166 T02780222
     * EFVR 231620Z AUTO 19002KT 5000 BR FEW003 BKN005 OVC007 09/08 Q0998
     * KTTN 051853Z 04011KT M1/2SM VCTS SN FZFG BKN003 OVC010 M02/M02 A3006 RMK AO2 TSB40 SLP176 P0002 T10171017=
     * UEEE 072000Z 00000MPS 0150 R23L/0500 R10/1000VP1800D FG VV003 M50/M53 Q1028 RETSRA R12/290395 R31/CLRD// R/SNOCLO WS RWY10L WS RWY11L TEMPO 4000 RADZ BKN010 RMK QBB080 OFE745
     * UKDR 251830Z 00000MPS CAVOK 08/07 Q1019 3619//60 NOSIG
     * UBBB 251900Z 34015KT 9999 FEW013 BKN030 16/14 Q1016 88CLRD70 NOSIG
     * UMMS 251936Z 19002MPS 9999 SCT006 OVC026 06/05 Q1015 R31/D NOSIG RMK QBB080 OFE745
     *
     * @param bool $taf
     * @param bool $debug
     * @param bool $icao
     */
    public function __construct($raw, $taf = false, $debug = false, $icao = true)
    {
        $this->debug_enabled = $debug;
        // Log::info('Parsing metar="'.$raw.'"');

        $raw_lines = explode("\n", $raw, 2);
        if (isset($raw_lines[1])) {
            $raw = trim($raw_lines[1]);
            // Get observed time from a file data
            $observed_time = strtotime(trim($raw_lines[0]));
            if ($observed_time !== 0) {
                $this->set_observed_date($observed_time);
            }
        } else {
            $raw = trim($raw_lines[0]);
        }

        $this->raw = rtrim(trim(preg_replace('/[\s\t]+/s', ' ', $raw)), '=');
        /*if ($taf) {
            $this->set_debug('Information presented as TAF or trend.');
        } else {
            $this->set_debug('Information presented as METAR.');
        }*/

        $this->set_result_value('taf', $taf);
        $this->set_result_value('raw', $this->raw);

        $this->parse_all();
    }

    /**
     * Shortcut to call.
     *
     * @param  string $taf
     * @return mixed
     */
    public static function parse($metar, $taf = '')
    {
        $mtr = new static($metar, $taf);

        return $mtr->parse_all();
    }

    /**
     * Gets the value from result array as class property.
     *
     *
     * @return mixed|null
     */
    public function __get($parameter)
    {
        if (isset($this->result[$parameter])) {
            return $this->result[$parameter];
        }
    }

    /**
     * Return an Altitude value or object.
     *
     * @param  int|float $value
     * @param  string    $unit  "feet" or "meters"
     * @return Altitude
     */
    protected function createAltitude($value, $unit)
    {
        return Altitude::make((float) $value, $unit);
    }

    /**
     * Return a Distance value or object.
     *
     * @param  int|float $value
     * @param  string    $unit  "m" (meters) or "mi" (miles)
     * @return Distance
     */
    protected function createDistance($value, $unit)
    {
        return Distance::make((float) $value, $unit);
    }

    /**
     * Return a Pressure value or object.
     *
     * @param  int|float $value
     * @param  string    $unit  "F" or "C"
     * @return Pressure
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    protected function createPressure($value, $unit)
    {
        return Pressure::make((float) $value, $unit);
    }

    /**
     * Return a Temperature value or object.
     *
     * @param  int|float   $value
     * @param  string      $unit  "F" or "C"
     * @return Temperature
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    protected function createTemperature($value, $unit)
    {
        return Temperature::make((float) $value, $unit);
    }

    /**
     * Create a new velocity unit.
     *
     * @param  int|float $value
     * @param  string    $unit  "knots", "km/hour", "m/s"
     * @return Velocity
     *
     * @throws NonStringUnitName
     * @throws NonNumericValue
     */
    protected function createVelocity($value, $unit)
    {
        return Velocity::make((float) $value, $unit);
    }

    /**
     * Parses the METAR or TAF information and returns result array.
     */
    public function parse_all(): array
    {
        $this->raw_parts = explode(' ', $this->raw);
        $current_method = 0;

        $raw_part_count = count($this->raw_parts);
        $method_name_count = count(static::$method_names);

        while ($this->part < $raw_part_count) {
            $this->method = $current_method;
            while ($this->method < $method_name_count) {
                $method = 'get_'.static::$method_names[$this->method];
                $token = $this->raw_parts[$this->part];
                if ($this->$method($token) === true) {
                    /*$this->set_debug('Token "'.$token.'" is parsed by method: '.$method.', '.
                        ($this->method - $current_method).' previous methods skipped.');*/
                    $current_method = $this->method;
                    $this->method++;
                    break;
                }
                $this->method++;
            }

            if ($current_method !== $this->method - 1) {
                /*$this->set_error('Unknown token: '.$this->raw_parts[$this->part]);
                $this->set_debug('Token "'.$this->raw_parts[$this->part].'" is NOT PARSED, '.
                    ($this->method - $current_method).' methods attempted.');*/
            }

            $this->part++;
        }

        // Delete null values from the TAF report
        if ($this->result['taf'] === true) {
            foreach ($this->result as $parameter => $value) {
                if ($value === null) {
                    unset($this->result[$parameter]);
                }
            }
        }

        // Finally determine if it's VFR or IFR conditions
        // https://www.aviationweather.gov/cva/help
        // https://www.skybrary.aero/index.php/Visual_Meteorological_Conditions_(VMC)
        // This may be changed to ICAO standards as VMC and IMC
        $this->result['category'] = 'VFR';

        if (array_key_exists('cavok', $this->result) && $this->result['cavok']) {
            $this->result['category'] = 'VFR';
        } else {
            /* @noinspection NestedPositiveIfStatementsInspection */
            if (array_key_exists('cloud_height', $this->result) && $this->result['cloud_height'] !== null) {
                if ($this->result['cloud_height']['ft'] > 3000
                    && (empty($this->result['visibility']) || $this->result['visibility']['km'] > 5)) {
                    $this->result['category'] = 'VFR';
                } else {
                    $this->result['category'] = 'IFR';
                }
            }
        }

        return $this->result;
    }

    /**
     * Returns array with debug information.
     */
    public function debug()
    {
        return $this->debug;
    }

    /**
     * Returns array with parse errors.
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * This method formats observation date and time in the local time zone of server,
     * the current local time on server, and time difference since observation. $time_utc is a
     * UNIX timestamp for Universal Coordinated Time (Greenwich Mean Time or Zulu Time).
     *
     * @param mixed $time_utc
     */
    private function set_observed_date($time_utc)
    {
        $now = time();
        $local = $time_utc; // + date('Z');

        $this->set_result_value('observed_date', date('r', $local)); // or "D M j, H:i T"
        $time_diff = floor(($now - $local) / 60);

        if ($time_diff < 91) {
            $this->set_result_value('observed_age', $time_diff.' '.trans_choice('widgets.weather.minago', $time_diff));
        } else {
            $this->set_result_value('observed_age', floor($time_diff / 60).':'.sprintf('%02d', $time_diff % 60).' '.trans_choice('widgets.weather.hrago', floor($time_diff / 60)));
        }
    }

    /**
     * Sets the new value to parameter in result array.
     *
     * @param bool $only_if_null
     */
    private function set_result_value($parameter, $value, $only_if_null = false)
    {
        if ($only_if_null) {
            if ($this->result[$parameter] === null) {
                $this->result[$parameter] = $value;
            }
        } else {
            $this->result[$parameter] = $value;
        }
    }

    /**
     * Sets the data group to parameter in result array.
     *
     * @param mixed $parameter
     * @param mixed $group
     */
    private function set_result_group($parameter, $group)
    {
        if ($this->result[$parameter] === null) {
            $this->result[$parameter] = [];
        }

        $this->result[$parameter][] = $group;
    }

    /**
     * Sets the report text to parameter in result array.
     *
     * @param string $separator
     */
    private function set_result_report($parameter, $report, $separator = ';')
    {
        $this->result[$parameter] .= $separator.' '.$report;
        if ($this->result[$parameter] !== null) {
            $this->result[$parameter] = ucfirst(ltrim($this->result[$parameter], ' '.$separator));
        }
    }

    /**
     * Adds the debug text to debug information array.
     *
     * @param mixed $text
     */
    private function set_debug($text)
    {
        if ($this->debug_enabled) {
            $this->debug[] = $text;
        }
    }

    /**
     * Adds the error text to parse errors array.
     *
     * @param mixed $text
     */
    private function set_error($text)
    {
        $this->errors[] = $text;
    }

    // --------------------------------------------------------------------
    // Methods for parsing raw parts
    // --------------------------------------------------------------------

    /**
     * Decodes TAF code if present.
     *
     * @param  mixed $part
     * @return bool
     */
    private function get_taf($part)
    {
        if ($part !== 'TAF') {
            return false;
        }

        if ($this->raw_parts[$this->part + 1] === 'COR'
            || $this->raw_parts[$this->part + 1] === 'AMD') {
            $this->set_result_value('taf_flag', $this->raw_parts[$this->part + 1], true);
            $this->part++;
        }

        // $this->set_debug('TAF information detected.');
        $this->set_result_value('taf', true);

        return true;
    }

    /**
     * Decodes station code.
     *
     * @param  mixed $part
     * @return bool
     */
    private function get_station($part)
    {
        $r = '@^([A-Z]{1}'.'[A-Z0-9]{3})$@';  // 1
        if (!preg_match($r, $part, $found)) {
            return false;
        }

        $this->set_result_value('station', $found[1]);
        $this->method++;

        return true;
    }

    /**
     * Decodes observation time.
     * Format is ddhhmmZ where dd = day, hh = hours, mm = minutes in UTC time.
     *
     * @param  mixed $part
     * @return bool
     */
    private function get_time($part)
    {
        $r = '@^([\d]{2})'   // 1
            .'([\d]{2})'     // 2
            .'([\d]{2})Z$@'; // 3

        if (!preg_match($r, $part, $found)) {
            return false;
        }

        $day = (int) $found[1];
        $hour = (int) $found[2];
        $minute = (int) $found[3];

        if ($this->result['observed_date'] === null) {
            // Take one month, if the observed day is greater than the current day
            if ($day > date('j')) {
                $month = date('n') - 1;
            } else {
                $month = date('n');
            }

            // Get observed time from a METAR/TAF part
            $observed_time = mktime($hour, $minute, 0, $month, $day, date('Y'));

            $this->set_observed_date($observed_time);
        }

        $this->set_result_value('observed_day', $day);
        $this->set_result_value('observed_time', $found[2].':'.$found[3].' UTC');
        $this->method++;

        return true;
    }

    /**
     * Ignore station type if present.
     *
     * @param  mixed $part
     * @return bool
     */
    private function get_station_type($part)
    {
        if ($part !== 'AUTO' && $part !== 'COR') {
            return false;
        }

        $this->method++;

        return true;
    }

    /**
     * Decodes wind direction and speed information.
     * Format is dddssKT where ddd = degrees from North, ss = speed, KT for knots,
     * or dddssGggKT where G stands for gust and gg = gust speed. (ss or gg can be a 3-digit number.)
     * KT can be replaced with MPH for meters per second or KMH for kilometers per hour.
     *
     *
     * @return bool
     */
    private function get_wind($part)
    {
        $r = '@^([\d]{3}|VRB|///)P?' // 1
            .'([/0-9]{2,3}|//)'      // 2
            .'(GP?'                  // 3
            .'([\d]{2,3}))?'         // 4
            .'(KT|MPS|KPH)@';        // 5

        if (!preg_match($r, $part, $found)) {
            return false;
        }

        $this->set_result_value('wind_direction_varies', false, true);

        if ($found[1] === '///' && $found[2] === '//') {
        } // handle the case where nothing is observed
        else {
            $unit = $found[5];

            // Speed
            $this->set_result_value('wind_speed', $this->convert_speed($found[2], $unit));

            // Direction
            if ($found[1] === 'VRB') {
                $this->set_result_value('wind_direction_varies', true);
            } else {
                $direction = (int) $found[1];
                if ($direction >= 0 && $direction <= 360) {
                    $this->set_result_value('wind_direction', $direction);
                    $this->set_result_value('wind_direction_label', $this->convert_direction_label($direction));
                }
            }

            // Speed variations (gust speed)
            if (isset($found[4]) && !empty($found[4])) {
                $this->set_result_value('wind_gust_speed', $this->convert_speed($found[4], $unit));
            }
        }

        $this->method++;

        return true;
    }

    /*
     * Decodes varies wind direction information if present.
     * Format is fffVttt where V stands for varies from fff degrees to ttt degrees.
     */
    private function get_varies_wind($part)
    {
        $r = '@^([\d]{3})'   // 1
            .'V([\d]{3})$@'; // 2

        if (!preg_match($r, $part, $found)) {
            return false;
        }

        $min_direction = (int) $found[1];
        $max_direction = (int) $found[2];

        if ($min_direction >= 0 && $min_direction <= 360) {
            $this->set_result_value('varies_wind_min', $min_direction);
            $this->set_result_value('varies_wind_min_label', $this->convert_direction_label($min_direction));
        }

        if ($max_direction >= 0 && $max_direction <= 360) {
            $this->set_result_value('varies_wind_max', $max_direction);
            $this->set_result_value('varies_wind_max_label', $this->convert_direction_label($max_direction));
        }

        $this->method++;

        return true;
    }

    /**
     * Decodes visibility information. This function will be called a second time
     * if visibility is limited to an integer mile plus a fraction part.
     * Format is mmSM for mm = statute miles, or m n/dSM for m = mile and n/d = fraction of a mile,
     * or just a 4-digit number nnnn (with leading zeros) for nnnn = meters.
     * Unit can also be in KM.
     *
     * @param  mixed $part
     * @return bool
     *
     * @throws NonStringUnitName
     * @throws NonNumericValue
     */
    private function get_visibility($part)
    {
        $r = '@^(CAVOK|([\d]{4})' // 1
            .'|(M)?'              // 2
            .'([\d]{0,2})?'       // 3
            .'(([1357])'          // 4
            .'/(2|4|8|16))?'      // 5
            .'(SM|KM|M|MI)|////)$@';        // 6

        if (!preg_match($r, $part, $found)) {
            return false;
        }

        $this->set_result_value('cavok', false, true);

        // Cloud and visibilty OK or ICAO visibilty greater than 10 km
        if (strtoupper($found[1]) === 'CAVOK' || $found[1] === '9999') {
            $this->set_result_value('visibility', $this->createDistance(10000, 'm'));
            $this->set_result_value('visibility_report', 'Greater than 10 km');
            /* @noinspection NotOptimalIfConditionsInspection */
            if (strtoupper($found[1]) === 'CAVOK') {
                $this->set_result_value('cavok', true);
                $this->method += 4; // can skip the next 4 methods: visibility_min, runway_vr, present_weather, clouds
            }
        } elseif ($found[1] === '////') {
        } // information not available

        else {
            $prefix = '';

            // ICAO visibility (in meters)
            if (isset($found[2]) && !empty($found[2])) {
                $visibility = $this->createDistance((int) $found[2], 'm');
            } else {
                if (isset($found[3]) && !empty($found[3])) {
                    $prefix = 'Less than ';
                }

                if (isset($found[7]) && !empty($found[7])) {
                    $visibility = (int) $found[4] + (int) $found[6] / (int) $found[7];
                } else {
                    $visibility = (int) $found[4];
                }

                $units = strtoupper($found[8]);
                if ($units == 'MI' || $units == 'SM') {
                    $unit = 'mi';
                } elseif ($units == 'M') {
                    $unit = 'm';
                } elseif ($units == 'KM') {
                    $unit = 'km';
                } else {
                    $unit = $units;
                }

                $visibility = $this->createDistance($visibility, $unit);
            }

            if ($visibility['m'] > 1000) {
                $unit = ' km';
                $report = $prefix.$visibility['km'].$unit;
            } else {
                $unit = ' meters';
                if ($visibility['m'] <= 1) {
                    $unit = ' meter';
                }

                $report = $prefix.$visibility['m'].$unit;
            }

            $this->set_result_value('visibility', $visibility);
            $this->set_result_value('visibility_report', $report);
        }

        return true;
    }

    /**
     * Decodes visibility minimum value and direction if present.
     * Format is vvvvDD for vvvv = the minimum horizontal visibility in meters
     * (if the visibility is better than 10 km, 9999 is used. 9999 means a minimum
     * visibility of 50 m or less), and for DD = the approximate direction of minimum and
     * maximum visibility is given as one of eight compass points (N, SW, ...).
     *
     *
     * @return bool
     *
     * @throws NonStringUnitName
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    private function get_visibility_min($part)
    {
        if (!preg_match('@^([\d]{4})(NE|NW|SE|SW|N|E|S|W|)?$@', $part, $found)) {
            return false;
        }

        $meters = $this->createDistance((int) $found[1], 'm');
        $this->set_result_value('visibility_min', $meters);

        if (isset($found[2]) && !empty($found[2])) {
            $this->set_result_value('visibility_min_direction', $found[2]);
        }

        $this->method++;

        return true;
    }

    /**
     * Decodes runway visual range information if present.
     * Format is Rrrr/vvvvFT where rrr = runway number, vvvv = visibility,
     * and FT = the visibility in feet.
     *
     *
     * @return bool
     *
     * @throws NonStringUnitName
     * @throws NonNumericValue
     */
    private function get_runway_vr($part)
    {
        $r = '@^R([\d]{2}[LCR]?)/'  // 1
            .'(([PM])?'             // 2
            .'([\d]{4})V)?'         // 3
            .'([PM])?([\d]{4})'     // 4
            .'(FT)?/?'              // 6
            .'([UDN]?)$@';          // 7

        if (!preg_match($r, $part, $found)) {
            return false;
        }

        if ((int) $found[1] > 36 || (int) $found[1] < 1) {
            return false;
        }

        $unit = 'meter';
        $report_unit = 'm';
        if (isset($found[6]) && $found[6] === 'FT') {
            $unit = 'feet';
            $report_unit = 'nmi';
        }

        $observed = [
            'runway'          => $found[1],
            'variable'        => null,
            'variable_prefix' => null,
            'interval_min'    => null,
            'interval_max'    => null,
            'tendency'        => null,
            'report'          => null,
        ];

        // Runway past tendency
        if (isset($found[8], static::$rvr_tendency_codes[$found[8]])) {
            $observed['tendency'] = $found[8];
        }

        // Runway visual range
        if (isset($found[6])) {
            if (!empty($found[4])) {
                $observed['interval_min'] = $this->createDistance($found[4], $unit);
                $observed['interval_max'] = $this->createDistance($found[6], $unit);
                if (!empty($found[5])) {
                    $observed['variable_prefix'] = $found[5];
                }
            } else {
                $observed['variable'] = $this->createDistance($found[6], $unit);
            }
        }

        // Runway visual range report
        if (!empty($observed['runway'])) {
            $report = [];
            if ($observed['variable'] !== null) {
                $report[] = $observed['variable'][$report_unit].$report_unit;
            } elseif ($observed['interval_min'] !== null && $observed['interval_max'] !== null) {
                if (isset(static::$rvr_prefix_codes[$observed['variable_prefix']])) {
                    $report[] = 'varying from a min. of '.$observed['interval_min'][$report_unit].$report_unit.' until a max. of '.
                        static::$rvr_prefix_codes[$observed['variable_prefix']].' that '.
                        $observed['interval_max'][$report_unit].' '.$report_unit;
                } else {
                    $report[] = 'varying from a min. of '.$observed['interval_min'][$report_unit].$report_unit.' until a max. of '.
                        $observed['interval_max'][$report_unit].$report_unit;
                }
            }

            if ($observed['tendency'] !== null && isset(static::$rvr_tendency_codes[$observed['tendency']])) {
                $report[] = 'and '.static::$rvr_tendency_codes[$observed['tendency']];
            }

            $observed['report'] = ucfirst(implode(' ', $report));
        }

        $this->set_result_group('runways_visual_range', $observed);

        return true;
    }

    /**
     * Decodes present weather conditions if present. This function maybe called several times
     * to decode all conditions. To learn more about weather condition codes, visit section
     * 12.6.8 - Present Weather Group of the Federal Meteorological Handbook No. 1 at
     * www.nws.noaa.gov/oso/oso1/oso12/fmh1/fmh1ch12.htm.
     *
     *
     * @return bool
     */
    private function get_present_weather($part)
    {
        return $this->decode_weather($part, 'present');
    }

    /**
     * Decodes cloud cover information if present. This function maybe called several times
     * to decode all cloud layer observations. Only the last layer is saved.
     * Format is SKC or CLR for clear skies, or cccnnn where ccc = 3-letter code and
     * nnn = height of cloud layer in hundreds of feet. 'VV' seems to be used for
     * very low cloud layers.
     *
     *
     * @return bool
     *
     * @throws NonStringUnitName
     * @throws NonNumericValue
     */
    private function get_clouds($part)
    {
        $r = '@^((NSW|NSC|NCD|CLR|SKC|NOBS)|'   // 1
            .'((VV|FEW|SCT|BKN|OVC|///)'        // 2
            .'([\d]{3}|///)'                    // 3
            .'(CB|TCU|///)?))$@';               // 4

        if (!preg_match($r, $part, $found)) {
            return false;
        }

        $observed = [
            'amount' => null,
            'height' => null,
            'type'   => null,
            'report' => null,
        ];

        // Clear skies or no observation
        if (isset($found[2]) && !empty($found[2])) {
            if (isset(static::$cloud_codes[$found[2]])) {
                $observed['amount'] = $found[2];
            }
        } // Cloud cover observed
        elseif (isset($found[5]) && !empty($found[5]) && is_numeric($found[5])) {
            $observed['height'] = $this->createAltitude($found[5] * 100, 'feet');

            // Cloud height
            if ($this->result['cloud_height'] === null || $observed['height']['m'] < $this->result['cloud_height']['m']) {
                $this->set_result_value('cloud_height', $observed['height']);
            }

            if (isset(static::$cloud_codes[$found[4]])) {
                $observed['amount'] = $found[4];
            }
        }
        // Type
        if (isset($found[6], static::$cloud_type_codes[$found[6]]) && !empty($found[6]) && $found[4] !== 'VV') {
            $observed['type'] = $found[6];
        }

        // Build clouds report
        if ($observed['amount'] !== null) {
            $report = [];
            $report_ft = [];

            $report[] = static::$cloud_codes[$observed['amount']];
            $report_ft[] = static::$cloud_codes[$observed['amount']];

            if ($observed['height']) {
                if ($observed['type'] !== null) {
                    $report[] = 'at '.round($observed['height']['m'], 0).' meters, '.static::$cloud_type_codes[$observed['type']];
                    $report_ft[] = 'at '.round($observed['height']['ft'], 0).' feet, '.static::$cloud_type_codes[$observed['type']];
                } else {
                    $report[] = 'at '.round($observed['height']['m'], 0).' meters';
                    $report_ft[] = 'at '.round($observed['height']['ft'], 0).' feet';
                }
            }

            $report = implode(' ', $report);
            $report_ft = implode(' ', $report_ft);

            $observed['report'] = $this->createAltitude($report_ft, 'ft');
            $observed['report'] = ucfirst($report);
            $observed['report_ft'] = ucfirst($report_ft);

            $this->set_result_report('clouds_report', $report);
            $this->set_result_report('clouds_report_ft', $report_ft);
        }

        $this->set_result_group('clouds', $observed);

        return true;
    }

    /**
     * Decodes temperature and dew point information. Relative humidity is calculated. Also,
     * depending on the temperature, Heat Index or Wind Chill Temperature is calculated.
     * Format is tt/dd where tt = temperature and dd = dew point temperature. All units are
     * in Celsius. A 'M' preceeding the tt or dd indicates a negative temperature. Some
     * stations do not report dew point, so the format is tt/ or tt/XX.
     *
     * @param  mixed $part
     * @return bool
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    private function get_temperature($part)
    {
        $r = '@^(M?[\d]{2})'    // 1
            .'/(M?[\d]{2}'      // 2
            .'|[X]{2})?@';      // 3
        if (!preg_match($r, $part, $found)) {
            return false;
        }

        // Set clouds and weather reports if its not observed (e.g. clear and dry)
        $this->set_result_value('clouds_report', 'Clear skies', true);
        $this->set_result_value('present_weather_report', 'Dry', true);

        // Temperature
        $temperature_c = (int) str_replace('M', '-', $found[1]);
        $temperature = $this->createTemperature($temperature_c, 'C');

        $this->set_result_value('temperature', $temperature);
        $this->calculate_wind_chill($temperature['F']);

        // Dew point
        if (isset($found[2]) && $found[2] !== '' && $found[2] !== 'XX') {
            $dew_point_c = (int) str_replace('M', '-', $found[2]);
            $dew_point = $this->createTemperature($dew_point_c, 'C');
            $rh = round(100 * (((112 - (0.1 * $temperature_c) + $dew_point_c) / (112 + (0.9 * $temperature_c))) ** 8));

            $this->set_result_value('dew_point', $dew_point);
            $this->set_result_value('humidity', $rh);
            $this->calculate_heat_index($temperature['F'], $rh);
        }

        $this->method++;

        return true;
    }

    /**
     * Decodes altimeter or barometer information.
     * Format is Annnn where nnnn represents a real number as nn.nn in inches of Hg,
     * or Qpppp where pppp = hectoPascals.
     * Some other common conversion factors:
     *   1 millibar = 1 hPa
     *   1 in Hg    = 0.02953 hPa
     *   1 mm Hg    = 25.4 in Hg     = 0.750062 hPa
     *   1 lb/sq in = 0.491154 in Hg = 0.014504 hPa
     *   1 atm      = 0.33421 in Hg  = 0.0009869 hPa.
     *
     *
     * @param  mixed $part
     * @return bool
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    private function get_pressure($part)
    {
        if (!preg_match('@^(Q|A)(////|[\d]{4})@', $part, $found)) {
            return false;
        }

        $pressure = (int) $found[2];
        if ($found[1] === 'A') {
            $pressure /= 100;
            $this->set_result_value('barometer', $this->createPressure($pressure, 'inHg'));
        } else {
            $this->set_result_value('barometer', $this->createPressure($pressure, 'hPa'));
        }

        $this->method++;

        return true;
    }

    /**
     * Decodes recent weather conditions if present.
     * Format is REww where ww = Weather phenomenon code (see get_present_weather above).
     *
     * @param  mixed $part
     * @return bool
     */
    private function get_recent_weather($part)
    {
        return $this->decode_weather($part, 'recent', 'RE');
    }

    /**
     * Decodes runways report information if present.
     * Format rrrECeeBB or Rrrr/ECeeBB where rr = runway number, E = deposits,
     * C = extent of deposit, ee = depth of deposit, BB = friction coefficient.
     *
     * @param  mixed $part
     * @return bool
     */
    private function get_runways_report($part)
    {
        $r = '@^R?'
            .'(/?(SNOCLO)'        // 1
            .'|([\d]{2}[LCR]?)/?' // 2
            .'(CLRD|([\d]{1}|/)'  // 3
            .'([\d]{1}|/)'        // 4
            .'([\d]{2}|//))'      // 5
            .'([\d]{2}|//))$@';   // 6

        if (!preg_match($r, $part, $found)) {
            return false;
        }

        $this->set_result_value('runways_snoclo', false, true);

        // Airport closed due to snow
        if (isset($found[2]) && $found[2] === 'SNOCLO') {
            $this->set_result_value('runways_snoclo', true);
        } else {
            $observed = [
                'runway'          => $found[3], // just runway number
                'deposits'        => null,
                'deposits_extent' => null,
                'deposits_depth'  => null,
                'friction'        => null,
                'report'          => null,
            ];
            // Contamination has disappeared (runway has been cleared)
            if (isset($found[4]) && $found[4] === 'CLRD') {
                $observed['deposits'] = 0; // cleared
            } // Deposits observed
            else {
                // Type
                $deposits = $found[5];
                if (isset(static::$runway_deposits_codes[$deposits])) {
                    $observed['deposits'] = $deposits;
                }

                // Extent
                $deposits_extent = $found[6];
                if (isset(static::$runway_deposits_extent_codes[$deposits_extent])) {
                    $observed['deposits_extent'] = $deposits_extent;
                }

                // Depth
                $deposits_depth = $found[7];

                // Uses in mm
                if ((int) $deposits_depth >= 1 && (int) $deposits_depth <= 90) {
                    $observed['deposits_depth'] = (int) $deposits_depth;
                } // Uses codes
                elseif (isset(static::$runway_deposits_depth_codes[$deposits_depth])) {
                    $observed['deposits_depth'] = $deposits_depth;
                }
            }

            // Friction observed
            $friction = $found[8];

            // Uses coefficient
            if ((int) $friction > 0 && (int) $friction <= 90) {
                $observed['friction'] = round($friction / 100, 2);
            } // Uses codes
            elseif (isset(static::$runway_friction_codes[$friction])) {
                $observed['friction'] = $friction;
            }

            // Build runways report
            $report = [];
            if ($observed['deposits'] !== null) {
                $report[] = static::$runway_deposits_codes[$observed['deposits']];
                if ($observed['deposits_extent'] !== null) {
                    $report[] = 'contamination '.static::$runway_deposits_extent_codes[$observed['deposits_extent']];
                }

                if ($observed['deposits_depth'] !== null) {
                    if ($observed['deposits_depth'] === '99') {
                        $report[] = 'runway closed';
                    } elseif (isset(static::$runway_deposits_depth_codes[$observed['deposits_depth']])) {
                        $report[] = 'deposit is '.static::$runway_deposits_depth_codes[$observed['deposits_depth']].' deep';
                    } else {
                        $report[] = 'deposit is '.$observed['deposits_depth'].' mm deep';
                    }
                }
            }

            if ($observed['friction'] !== null) {
                if (isset(static::$runway_friction_codes[$observed['friction']])) {
                    $report[] = 'a braking action is '.static::$runway_friction_codes[$observed['friction']];
                } else {
                    $report[] = 'a friction coefficient is '.$observed['friction'];
                }
            }

            $observed['report'] = ucfirst(implode(', ', $report));
            $this->set_result_group('runways_report', $observed);
        }

        return true;
    }

    /**
     * Decodes wind shear information if present.
     * Format is 'WS ALL RWY' or 'WS RWYdd' where dd = Runway designator (see get_runway_vr above).
     *
     * @param  mixed $part
     * @return bool
     */
    private function get_wind_shear($part)
    {
        if ($part !== 'WS') {
            return false;
        }

        $this->set_result_value('wind_shear_all_runways', false, true);
        $this->part++; // skip this part with WS

        // See two next parts for 'ALL RWY' records
        if (implode(' ', \array_slice($this->raw_parts, $this->part, 2)) === 'ALL RWY') {
            $this->set_result_value('wind_shear_all_runways', true);
            $this->part += 2; // can skip neext parts with ALL and RWY records
        } // See one next part for RWYdd record
        elseif (isset($this->raw_parts[$this->part])) {
            $r = '@^R(WY)?'           // 1
                .'([\d]{2}[LCR]?)$@'; // 2

            $part = $this->raw_parts[$this->part];
            if (!preg_match($r, $part, $found)) {
                return false;
            }

            if ((int) $found[2] > 36 || (int) $found[2] < 1) {
                return false;
            }

            $this->set_result_group('wind_shear_runways', $found[2]);
        } else {
            return false;
        }

        return true;
    }

    /**
     * Decodes max and min temperature forecast information if present.
     *
     * @param string $part
     *                     Format TXTtTt/ddHHZ or TNTtTt/ddHHZ, where:
     *                     TX   - Indicator for Maximum temperature
     *                     TN   - Indicator for Minimum temperature
     *                     TtTt - Temperature value in Celsius
     *                     dd   - Forecast day of month
     *                     HH   - Forecast hour, i.e. the time(hour) when the temperature is expected
     *                     Z    - Time Zone indicator, Z=GMT.
     *
     * @throws NonStringUnitName
     * @throws NonNumericValue
     */
    private function get_forecast_temperature($part): bool
    {
        $r = '@^(TX|TN)'     // 1
            .'(M?[\d]{2})'   // 2
            .'/([\d]{2})?'   // 3
            .'([\d]{2})Z$@'; // 4

        if (!preg_match($r, $this->raw_parts[$this->part], $found)) {
            return false;
        }

        // Temperature
        $temperature_c = (int) str_replace('M', '-', $found[2]);
        $temperture = $this->createTemperature($temperature_c, 'C');

        $forecast = [
            'value' => $temperture,
            'day'   => null,
            'time'  => null,
        ];

        if (!empty($found[3])) {
            $forecast['day'] = (int) $found[3];
        }

        $forecast['time'] = $found[4].':00 UTC';

        $parameter = 'forecast_temperature_max';
        if ($found[1] === 'TN') {
            $parameter = 'forecast_temperature_min';
        }

        $this->set_result_group($parameter, $forecast);

        return true;
    }

    /**
     * Decodes trends information if present.
     * All METAR trend and TAF records is beginning at: NOSIG, BECMG, TEMP, ATDDhhmm, FMDDhhmm,
     * LTDDhhmm or DDhh/DDhh, where hh = hours, mm = minutes, DD = day of month.
     *
     * @param  mixed $part
     * @return bool
     */
    private function get_trends($part)
    {
        $r = '@^((NOSIG|BECMG|TEMPO|INTER|CNL|NIL|PROV|(PROB)' // 1
            .'([\d]{2})|'     // 2
            .'(AT|FM|TL)'     // 3
            .'([\d]{2})?'     // 4
            .'([\d]{2})'      // 5
            .'([\d]{2}))|'    // 6
            .'(([\d]{2})'     // 7
            .'([\d]{2}))/'    // 8
            .'(([\d]{2})'     // 9
            .'([\d]{2})))$@'; // 10

        if (!preg_match($r, $part, $found)) {
            return false;
        }

        // Ignore trends
        return true;
        // Detects TAF on report
        if ($this->part <= 4) {
            $this->set_result_value('taf', true);
        }

        // Nil significant changes, skip trend
        if ($found[2] === 'NOSIG') {
            return true;
        }

        $trend = [
            'flag'          => null,
            'probability'   => null,
            'period_report' => null,
            'period'        => [
                'flag'      => null,
                'day'       => null,
                'time'      => null,
                'from_day'  => null,
                'from_time' => null,
                'to_day'    => null,
                'to_time'   => null,
            ],
        ];

        $raw_parts = [];

        // Get all parts after trend part
        while ($this->part < count($this->raw_parts)) {
            if (preg_match($r, $this->raw_parts[$this->part], $found)) {
                // Get trend flag
                if (isset($found[2], static::$trends_flag_codes[$found[2]])) {
                    $trend['flag'] = $found[2];
                } // Get PROBpp formatted period

                elseif (isset($found[3]) && $found[3] === 'PROB') {
                    $trend['probability'] = $found[4];
                } // Get AT, FM, TL formatted period

                elseif (isset($found[8], static::$trends_time_codes[$found[5]])) {
                    $trend['period']['flag'] = $found[5];
                    if (!empty($found[6])) {
                        $trend['period']['day'] = (int) $found[6];
                    }
                    $trend['period']['time'] = $found[7].':'.$found[8].' UTC';
                } // Get DDhh/DDhh formatted period

                elseif (isset($found[14])) {
                    $trend['period']['from_day'] = $found[10];
                    $trend['period']['from_time'] = $found[11].':00 UTC';
                    $trend['period']['to_day'] = $found[13];
                    $trend['period']['to_time'] = $found[14].':00 UTC';
                }
            } // If RMK observed -- the trend is ended

            elseif ($this->raw_parts[$this->part] === 'RMK') {
                if (!empty($raw_parts)) {
                    $this->part--; // return pointer to RMK part
                }

                break;
            } // Other data addrs to METAR raw

            else {
                $raw_parts[] = $this->raw_parts[$this->part];
            }

            $this->part++; // go to next part

            // Detect ends of this trend, if the METAR raw data observed
            if (!empty($raw_parts) && (!isset($this->raw_parts[$this->part]) || preg_match($r, $this->raw_parts[$this->part]))) {
                $this->part--; // return pointer to finded part
                break;
            }
        }

        // Empty trend is a bad trend, except for flags CNL and NIL
        if (empty($raw_parts)) {
            if ($trend['flag'] !== 'CNL' && $trend['flag'] !== 'NIL') {
                $this->part--; // return pointer to previous part

                return false;
            }
        } // Parse raw data from trend

        else {
            $parser = new static(implode(' ', $raw_parts), true, $this->debug_enabled, false);
            if ($parsed = $parser->parse_all()) {
                unset($parsed['taf']);
                // Add parsed data to trend
                if (!empty($parsed)) {
                    $trend = array_merge($trend, $parsed);
                }
            }

            // Process debug messages
            /*if ($debug = $parser->debug()) {
                foreach ($debug as $message) {
                    $this->set_debug('Recursion: '.$message);
                }
            }*/

            // Process parse errors
            if ($errors = $parser->errors()) {
                foreach ($errors as $message) {
                    $this->set_error('Recursion: '.$message);
                }
            }
        }
        // Build the report
        $report = [];
        if ($trend['flag'] !== null) {
            $report[] = static::$trends_flag_codes[$trend['flag']];
        }

        if ($trend['period']['flag'] !== null) {
            if ($trend['period']['day'] !== null) {
                $report[] = static::$trends_time_codes[$trend['period']['flag']].
                    ' a '.$trend['period']['day'].' day of the month on '.$trend['period']['time'];
            } else {
                $report[] = static::$trends_time_codes[$trend['period']['flag']].' '.$trend['period']['time'];
            }
        }

        if ($trend['period']['from_day'] !== null && $trend['period']['to_day'] !== null) {
            $report[] = 'from a '.$trend['period']['from_day'].' day of the month on '.$trend['period']['from_time'];
            $report[] = 'to a '.$trend['period']['to_day'].' day of the month on '.$trend['period']['to_time'];
        }

        if ($trend['probability'] !== null) {
            $report[] = 'probability '.$trend['probability'].'% of the conditions existing';
        }

        if (!empty($report)) {
            $trend['period_report'] = ucfirst(implode(', ', $report));
        }

        $this->set_result_group('trends', $trend);

        return true;
    }

    /**
     * Get remarks information if present.
     * The information is everything that comes after RMK.
     *
     * @param string $part
     */
    private function get_remarks($part): bool
    {
        if ($part !== 'RMK') {
            return false;
        }

        $this->part++; // skip this part with RMK

        $remarks = [];
        // Get all parts after
        while ($this->part < count($this->raw_parts)) {
            if (isset($this->raw_parts[$this->part])) {
                $remarks[] = $this->raw_parts[$this->part];
            }
            $this->part++; // go to next part
        }

        if (!empty($remarks)) {
            $this->set_result_value('remarks', implode(' ', $remarks));
        }

        $this->method++;

        return true;
    }

    /**
     * Decodes present or recent weather conditions.
     *
     * @param  string $regexp_prefix
     * @return bool
     */
    private function decode_weather($part, $method, $regexp_prefix = '')
    {
        $wx_codes = implode('|', array_keys(array_merge(static::$weather_char_codes, static::$weather_type_codes)));
        if (!preg_match('@^'.$regexp_prefix.'([-+]|VC)?('.$wx_codes.')?('.$wx_codes.')?('.$wx_codes.')?('.$wx_codes.')@', $part, $found)) {
            return false;
        }

        $observed = [
            'intensity'       => null,
            'types'           => null,
            'characteristics' => null,
            'report'          => null,
        ];

        // Intensity
        if ($found[1] !== null) {
            $observed['intensity'] = $found[1];
        }

        foreach (\array_slice($found, 1) as $code) {
            // Types
            if (isset(static::$weather_type_codes[$code])) {
                if ($observed['types'] === null) {
                    $observed['types'] = [];
                }

                $observed['types'][] = $code;
            }

            // Characteristics (uses last)
            if (isset(static::$weather_char_codes[$code])) {
                $observed['characteristics'] = $code;
            }
        }

        // Build recent weather report
        if ($observed['characteristics'] !== null || $observed['types'] !== null) {
            $report = [];
            if ($observed['intensity'] !== null) {
                if ($observed['intensity'] === 'VC') {
                    $report[] = static::$weather_intensity_codes[$observed['intensity']].',';
                } else {
                    $report[] = static::$weather_intensity_codes[$observed['intensity']];
                }
            }

            if ($observed['characteristics'] !== null) {
                $report[] = static::$weather_char_codes[$observed['characteristics']];
            }

            if ($observed['types'] !== null) {
                foreach ($observed['types'] as $code) {
                    $report[] = static::$weather_type_codes[$code];
                }
            }

            $report = implode(' ', $report);
            $observed['report'] = ucfirst($report);

            $this->set_result_report($method.'_weather_report', $report);
        }

        $this->set_result_group($method.'_weather', $observed);

        return true;
    }

    /**
     * Calculate Heat Index based on temperature in F and relative humidity (65 = 65%).
     *
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    private function calculate_heat_index($temperature_f, $rh): void
    {
        if ($temperature_f > 79 && $rh > 39) {
            $hi_f = -42.379 + 2.04901523 * $temperature_f + 10.14333127 * $rh - 0.22475541 * $temperature_f * $rh;
            $hi_f += -0.00683783 * ($temperature_f ** 2) - 0.05481717 * ($rh ** 2);
            $hi_f += 0.00122874 * ($temperature_f ** 2) * $rh + 0.00085282 * $temperature_f * ($rh ** 2);
            $hi_f += -0.00000199 * ($temperature_f ** 2) * ($rh ** 2);
            $hi_f = round($hi_f);
            $hi_c = round(($hi_f - 32) / 1.8);

            $this->set_result_value('heat_index', $this->createTemperature($hi_c, 'C'));
        }
    }

    /**
     * Calculate Wind Chill Temperature based on temperature in F
     * and wind speed in miles per hour.
     *
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    private function calculate_wind_chill($temperature_f): void
    {
        if ($temperature_f < 51 && $this->result['wind_speed'] && $this->result['wind_speed'] !== 0) {
            $windspeed = $this->result['wind_speed']->toUnit('mph');
            if ($windspeed > 3) {
                $chill_f = 35.74 + 0.6215 * $temperature_f - 35.75 * ($windspeed ** 0.16);
                $chill_f += 0.4275 * $temperature_f * ($windspeed ** 0.16);
                $chill_f = round($chill_f);
                $chill_c = round(($chill_f - 32) / 1.8);

                $this->set_result_value('wind_chill', $this->createTemperature($chill_c, 'C'));
            }
        }
    }

    /**
     * Convert wind speed into meters per second.
     * Some other common conversion factors:
     *   1 mi/hr = 0.868976 knots  = 0.000447 km/hr = 0.44704  m/s  = 1.466667 ft/s
     *   1 ft/s  = 0.592483 knots  = 1.097279 km/hr = 0.304799 m/s  = 0.681818 mi/hr
     *   1 knot  = 1.852    km/hr  = 0.514444 m/s   = 1.687809 ft/s = 1.150779 mi/hr
     *   1 km/hr = 0.539957 knots  = 0.277778 m/s   = 0.911344 ft/s = 0.621371 mi/hr
     *   1 m/s   = 1.943844 knots  = 3.6      km/h  = 3.28084  ft/s = 2.236936 mi/hr.
     *
     *
     * @return Velocity
     *
     * @throws NonStringUnitName
     * @throws NonNumericValue
     */
    private function convert_speed($speed, $unit)
    {
        // TODO: return dict w/ multiple units - NS

        switch ($unit) {
            case 'KT':
                return $this->createVelocity($speed, 'knots');
            case 'KPH':
                return $this->createVelocity($speed, 'km/hour');
            default:
                return $this->createVelocity($speed, 'm/s');
        }
    }

    /**
     * Convert direction degrees to compass label.
     *
     * @param  mixed  $direction
     * @return string Direction string
     */
    private function convert_direction_label($direction): string
    {
        if ($direction >= 0 && $direction <= 360) {
            return static::$direction_codes[round($direction / 22.5) % 16];
        }

        return 'N';
    }

    /**
     * These methods below the implementation of the stubs for ArrayAccess.
     *
     * @param mixed $offset
     */

    /**
     * Whether a offset exists.
     *
     * @link  http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param  mixed $offset <p>
     *                       An offset to check for.
     *                       </p>
     * @return bool  true on success or false on failure.
     *               </p>
     *               <p>
     *               The return value will be casted to boolean if non-boolean was returned.
     *
     * @since 5.0.0
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->result);
    }

    /**
     * Offset to retrieve.
     *
     * @link  http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param  mixed $offset <p>
     *                       The offset to retrieve.
     *                       </p>
     * @return mixed Can return all value types.
     *
     * @since 5.0.0
     */
    public function offsetGet($offset): mixed
    {
        return $this->result[$offset];
    }

    /**
     * Offset to set.
     *
     * @link  http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value  <p>
     *                      The value to set.
     *                      </p>
     *
     * @since 5.0.0
     */
    public function offsetSet($offset, $value): void
    {
        $this->result[$offset] = $value;
    }

    /**
     * Offset to unset.
     *
     * @link  http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     *                      The offset to unset.
     *                      </p>
     *
     * @since 5.0.0
     */
    public function offsetUnset($offset): void
    {
        $this->result[$offset] = null;
    }
}
