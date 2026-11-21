<?php

declare(strict_types=1);

namespace App\Features\Setup\PostUpgrade;

use RuntimeException;
use SimpleXMLElement;

/**
 * Turns a 7.x SimBrief OFP XML into the array SimBrief's `json=v2` fetch returns,
 * the shape 8.x stores and acars reads. Paths are dotted; `*` is one list index or key.
 */
final class SimBriefXmlToJson
{
    // Always lists, even with one item; an empty element is [].
    private const array LISTS = [
        'general.dx_rmk', 'general.sys_rmk',
        'alternate', 'takeoff_altn', 'enroute_altn', 'enroute_station', 'alternate_navlog',
        'origin.atis', 'origin.notam', 'destination.atis', 'destination.notam',
        'alternate.*.atis', 'alternate.*.notam', 'takeoff_altn.*.atis', 'takeoff_altn.*.notam',
        'enroute_altn.*.atis', 'enroute_altn.*.notam',
        'tlr.takeoff.runway', 'tlr.landing.runway', 'tlr.takeoff.runway.*.limit_obstacle',
        'atc.fir_altn', 'atc.fir_etops', 'atc.fir_enroute',
        'crew.fa', 'fuel_extra.bucket',
        'weather.altn_metar', 'weather.altn_taf', 'weather.etops_metar', 'weather.etops_taf',
        'files.file', 'images.map', 'tracks.nat', 'etops.suitable_airport', 'etops.equal_time_point',
    ];

    // Wrapper element => its repeated child: v2 drops the wrapper and keeps the list.
    private const array UNWRAP = [
        'navlog'                            => 'fix',
        'navlog.*.wind_data'                => 'level',
        'navlog.*.fir_crossing'             => 'fir',
        'alternate_navlog.*'                => 'fix',
        'alternate_navlog.*.*.wind_data'    => 'level',
        'alternate_navlog.*.*.fir_crossing' => 'fir',
        'notams'                            => 'notamdrec',
        'sigmets'                           => 'sigmet',
        'tracks.nat.*.fixes'                => 'fix',
    ];

    // Objects SimBrief sends as [] when it has none.
    private const array EMPTY_AS_ARRAY = ['etops', 'tracks', 'impacts.*'];

    // The XML has epoch seconds; v2 has ISO 8601 UTC.
    private const array DATES = [
        'params.time_generated',
        'times.sched_out', 'times.sched_off', 'times.sched_on', 'times.sched_in',
        'times.est_out', 'times.est_off', 'times.est_on', 'times.est_in',
        'database_updates.*', 'tracks.nat.*.start', 'tracks.nat.*.end',
    ];

    // The XML has seconds; v2 has HH:MM:SS.
    private const array DURATIONS = [
        'times.est_time_enroute', 'times.sched_time_enroute', 'times.sched_block', 'times.est_block',
        'times.taxi_out', 'times.taxi_in', 'times.reserve_time', 'times.endurance',
        'times.contfuel_time', 'times.etopsfuel_time', 'times.extrafuel_time',
        'navlog.*.time_leg', 'navlog.*.time_total',
        'alternate_navlog.*.*.time_leg', 'alternate_navlog.*.*.time_total',
        'alternate.*.ete', 'impacts.*.time_enroute',
    ];

    private const array SIGNED_DURATIONS = ['impacts.*.time_difference'];

    private const array SIGNED_NUMBERS = ['impacts.*.burn_difference'];

    // Hours from UTC, e.g. 5.5; v2 has +05:30:00.
    private const array TIMEZONES = ['times.orig_timezone', 'times.dest_timezone'];

    private const array BOOLEANS = [
        'fuel_extra.bucket.*.required',
        '*.notam.*.date_expire_is_estimated', '*.notam.*.notam_is_obstacle',
        '*.*.notam.*.date_expire_is_estimated', '*.*.notam.*.notam_is_obstacle',
    ];

    /** @var array<string, string> */
    private static array $regex = [];

    /**
     * @return array<string, mixed>
     */
    public function convert(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            $error = libxml_get_last_error();
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        // acars flight plans carry a trimmed OFP under a `<VMSAcars>` root.
        if (!$root instanceof SimpleXMLElement || !in_array($root->getName(), ['OFP', 'VMSAcars'], true)) {
            throw new RuntimeException('Not a SimBrief OFP: '.($error === false ? 'no <OFP> root' : trim($error->message)));
        }

        /** @var array<string, mixed> $ofp */
        $ofp = $this->object($root, '');

        $params = is_array($ofp['params'] ?? null) ? $ofp['params'] : [];

        // The XML has no fetch block; the DTO requires the one a successful fetch carries.
        return [
            'fetch' => [
                'userid'    => $params['user_id'] ?? '',
                'static_id' => $params['static_id'] ?? '',
                'status'    => 'Success',
                'time'      => '0',
            ],
            ...$ofp,
        ];
    }

    private function element(SimpleXMLElement $el, string $path): mixed
    {
        if (($child = $this->match(self::UNWRAP, $path)) !== null) {
            $items = [];
            foreach ($el->children() as $name => $item) {
                if ($name === $child) {
                    $items[] = $this->element($item, $path.'.'.count($items));
                }
            }

            return $items;
        }

        if ($el->children()->count() > 0) {
            return $this->object($el, $path);
        }

        $text = (string) $el;

        if ($text === '') {
            return $this->match(self::EMPTY_AS_ARRAY, $path) === null ? '' : [];
        }

        return $this->scalar($text, $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function object(SimpleXMLElement $el, string $path): array
    {
        $out = [];
        $repeated = [];

        foreach ($el->children() as $name => $child) {
            $childPath = ltrim($path.'.'.$name, '.');

            if ($this->match(self::LISTS, $childPath) !== null) {
                $out[$name] ??= [];
                // A lone empty element is an empty list, not a list of one ''.
                if ($child->children()->count() === 0 && (string) $child === '') {
                    continue;
                }

                $out[$name][] = $this->element($child, $childPath.'.'.count($out[$name]));

                continue;
            }

            $value = $this->element($child, $childPath);

            if (!array_key_exists($name, $out)) {
                $out[$name] = $value;
            } elseif (isset($repeated[$name])) {
                $out[$name][] = $value;
            } else {
                $out[$name] = [$out[$name], $value];
                $repeated[$name] = true;
            }
        }

        // SimBrief leaves out a list it has nothing for (crew.fa with no cabin crew).
        foreach (self::LISTS as $list) {
            $at = strrpos($list, '.');
            $parent = $at === false ? '' : substr($list, 0, $at);

            if ($this->match([$parent], $path) !== null) {
                $out[$at === false ? $list : substr($list, $at + 1)] ??= [];
            }
        }

        return $out;
    }

    private function scalar(string $text, string $path): string|bool
    {
        if ($this->match(self::BOOLEANS, $path) !== null) {
            return in_array(strtolower(trim($text)), ['1', 'true'], true);
        }

        if (!preg_match('/^-?\d+(\.\d+)?$/', trim($text))) {
            return $text;
        }

        $number = (float) trim($text);

        return match (true) {
            $this->match(self::DATES, $path) !== null            => gmdate('Y-m-d\TH:i:s\Z', (int) $number),
            $this->match(self::DURATIONS, $path) !== null        => $this->hms((int) $number),
            $this->match(self::SIGNED_DURATIONS, $path) !== null => ($number < 0 ? '-' : '+').$this->hms((int) abs($number)),
            $this->match(self::SIGNED_NUMBERS, $path) !== null   => ($number < 0 ? '' : '+').trim($text),
            $this->match(self::TIMEZONES, $path) !== null        => ($number < 0 ? '-' : '+').$this->hms((int) round(abs($number) * 3600)),
            default                                              => $text,
        };
    }

    private function hms(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Matches with list indexes normalised to `*`; returns the map value, or the pattern for a list.
     *
     * @param array<int|string, string> $patterns
     */
    private function match(array $patterns, string $path): ?string
    {
        foreach ($patterns as $key => $value) {
            $pattern = is_string($key) ? $key : $value;
            self::$regex[$pattern] ??= '/^'.str_replace('\*', '[^.]+', preg_quote($pattern, '/')).'$/';

            if (preg_match(self::$regex[$pattern], $path) === 1) {
                return $value;
            }
        }

        return null;
    }
}
