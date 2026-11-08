<?php

declare(strict_types=1);

namespace App\Features\Flights\Actions;

use App\Http\Data\MetarDescriptionData;
use App\Support\Metar;

/**
 * Turn a parsed METAR into one plain sentence and a Meteocons icon, the way
 * an ACARS weather message would read it.
 */
final readonly class DescribeMetar
{
    private const array COMPASS = [
        'northerly', 'north-easterly', 'easterly', 'south-easterly',
        'southerly', 'south-westerly', 'westerly', 'north-westerly',
    ];

    private const array SKY = [
        'FEW' => 'Few clouds',
        'SCT' => 'Scattered clouds',
        'BKN' => 'Broken clouds',
        'OVC' => 'Overcast',
        'VV'  => 'Sky obscured',
    ];

    private const array CHARACTERISTICS = [
        'MI' => 'shallow',
        'PR' => 'partial',
        'BC' => 'patches of',
        'DR' => 'low drifting',
        'BL' => 'blowing',
        'FZ' => 'freezing',
    ];

    private const array TYPES = [
        'DZ' => 'drizzle',
        'RA' => 'rain',
        'SN' => 'snow',
        'SG' => 'snow grains',
        'IC' => 'ice crystals',
        'PE' => 'ice pellets',
        'GR' => 'hail',
        'GS' => 'small hail',
        'UP' => 'precipitation',
        'BR' => 'mist',
        'FG' => 'fog',
        'FU' => 'smoke',
        'VA' => 'volcanic ash',
        'DU' => 'dust',
        'SA' => 'sand',
        'HZ' => 'haze',
        'SQ' => 'squalls',
        'FC' => 'funnel cloud',
    ];

    public function handle(Metar $metar, ?float $lat = null, ?float $lon = null): MetarDescriptionData
    {
        $category = is_string($metar->result['category']) ? $metar->result['category'] : null;
        $layers = $this->layers($metar);
        $groups = is_array($metar->result['present_weather']) ? $metar->result['present_weather'] : [];
        $temperature = $metar->temperature;
        $wind = $this->wind($metar);

        if ($metar->result['station'] === null && $layers === [] && $wind === null && $temperature === null && $metar->result['cavok'] !== true) {
            return new MetarDescriptionData('not-available-fill', 'Weather unavailable.', $category);
        }

        $isDay = $this->isDay($metar, $lat, $lon);
        $parts = [];

        $weather = $this->presentWeather($groups);
        if ($weather !== null) {
            $parts[] = $weather.'.';
        }

        $sky = $this->sky($metar, $layers);
        if (isset($temperature['celsius'])) {
            $sky .= ', '.round((float) $temperature['celsius']).'°C';
        }

        $parts[] = $sky.'.';

        if ($wind !== null) {
            $parts[] = $wind.'.';
        }

        return new MetarDescriptionData(
            icon: $this->icon($groups, $metar->result['cavok'] === true ? null : $this->skyLayer($layers), $isDay),
            sentence: implode(' ', $parts),
            category: $category,
        );
    }

    /**
     * Cloud layers, lowest first. Metar drops the cover of a layer whose height
     * is not reported (`BKN///`), so those are read back from the raw report and
     * sort after the layers with a known height.
     *
     * @return list<array{amount: string, ft: int|null}>
     */
    private function layers(Metar $metar): array
    {
        $layers = [];

        foreach (is_array($metar->clouds) ? $metar->clouds : [] as $cloud) {
            $amount = $cloud['amount'] ?? null;
            if (!isset(self::SKY[$amount]) || $cloud['height'] === null) {
                continue;
            }

            $layers[] = ['amount' => $amount, 'ft' => (int) round($cloud['height']['ft'])];
        }

        $raw = is_string($metar->result['raw']) ? $metar->result['raw'] : '';
        preg_match_all('/(?<=\s)(VV|FEW|SCT|BKN|OVC)\/\/\/(?:CB|TCU|\/\/\/)?(?=\s|$)/', $raw, $unknown);
        foreach ($unknown[1] as $amount) {
            $layers[] = ['amount' => $amount, 'ft' => null];
        }

        usort($layers, static fn (array $a, array $b): int => ($a['ft'] ?? PHP_INT_MAX) <=> ($b['ft'] ?? PHP_INT_MAX));

        return $layers;
    }

    /**
     * The ceiling (lowest broken, overcast or vertical visibility), else the lowest layer.
     *
     * @param  list<array{amount: string, ft: int|null}> $layers
     * @return array{amount: string, ft: int|null}|null
     */
    private function skyLayer(array $layers): ?array
    {
        foreach ($layers as $layer) {
            if (in_array($layer['amount'], ['BKN', 'OVC', 'VV'], true)) {
                return $layer;
            }
        }

        return $layers[0] ?? null;
    }

    /**
     * @param list<array{amount: string, ft: int|null}> $layers
     */
    private function sky(Metar $metar, array $layers): string
    {
        $layer = $metar->result['cavok'] === true ? null : $this->skyLayer($layers);
        if ($layer === null) {
            return 'Clear skies';
        }

        if ($layer['ft'] === null) {
            return self::SKY[$layer['amount']];
        }

        return self::SKY[$layer['amount']].' at '.number_format($layer['ft']).' ft';
    }

    private function wind(Metar $metar): ?string
    {
        $speed = $metar->wind_speed;
        if ($speed === null) {
            return null;
        }

        $knots = (int) round($speed->toUnit('knots'));
        if ($knots === 0) {
            return 'Wind calm';
        }

        if ($metar->result['wind_direction_varies'] === true) {
            $text = 'Wind variable at '.$knots.' knots';
        } elseif ($metar->wind_direction === null) {
            $text = 'Wind at '.$knots.' knots';
        } else {
            $compass = self::COMPASS[(int) round($metar->wind_direction / 45) % 8];
            $text = 'Wind '.$compass.' at '.$knots.' knots';
        }

        $gust = $metar->result['wind_gust_speed'];
        if ($gust !== null) {
            $text .= ', gusting '.(int) round($gust->toUnit('knots')).' knots';
        }

        return $text;
    }

    /**
     * @param list<array{intensity: ?string, types: ?list<string>, characteristics: ?string}> $groups
     */
    private function presentWeather(array $groups): ?string
    {
        $phrases = [];

        foreach ($groups as $group) {
            $types = array_map(static fn (string $code): string => self::TYPES[$code] ?? $code, $group['types'] ?? []);
            $characteristic = $group['characteristics'] ?? null;
            $thunderstorm = $characteristic === 'TS';
            $showers = $characteristic === 'SH';

            $words = [];
            $words[] = match ($group['intensity'] ?? '') {
                '-'     => 'light',
                '+'     => 'heavy',
                default => null,
            };

            if ($characteristic !== null && isset(self::CHARACTERISTICS[$characteristic])) {
                $words[] = self::CHARACTERISTICS[$characteristic];
            }

            $body = implode(' and ', $types);
            if ($thunderstorm) {
                $words[] = $body === '' ? 'thunderstorm' : 'thunderstorm with '.$body;
            } elseif ($showers) {
                $words[] = $body === '' ? 'showers' : $body.' showers';
            } elseif ($body !== '') {
                $words[] = $body;
            }

            $phrase = trim(implode(' ', array_filter($words)));
            if ($phrase === '') {
                continue;
            }

            if (($group['intensity'] ?? '') === 'VC') {
                $phrase .= ' in the vicinity';
            }

            $phrases[] = $phrase;
        }

        return $phrases === [] ? null : ucfirst(implode(', ', $phrases));
    }

    /**
     * @param list<array{intensity: ?string, types: ?list<string>, characteristics: ?string}> $groups
     * @param array{amount: string, ft: int|null}|null                                        $layer
     */
    private function icon(array $groups, ?array $layer, bool $isDay): string
    {
        $time = $isDay ? 'day' : 'night';
        $types = [];
        $thunderstorm = false;

        foreach ($groups as $group) {
            $types = [...$types, ...($group['types'] ?? [])];
            $thunderstorm = $thunderstorm || ($group['characteristics'] ?? null) === 'TS';
        }

        $has = static fn (string ...$codes): bool => array_intersect($codes, $types) !== [];

        return match (true) {
            $thunderstorm                                    => 'thunderstorms-'.$time.'-fill',
            $has('SN', 'SG')                                 => 'snow-fill',
            $has('PE', 'IC')                                 => 'sleet-fill',
            $has('GR', 'GS')                                 => 'hail-fill',
            $has('RA', 'UP')                                 => 'rain-fill',
            $has('DZ')                                       => 'drizzle-fill',
            $has('FG')                                       => 'fog-fill',
            $has('BR')                                       => 'mist-fill',
            $has('HZ', 'FU', 'VA', 'DU', 'SA')               => 'haze-'.$time.'-fill',
            $layer === null                                  => 'clear-'.$time.'-fill',
            in_array($layer['amount'], ['FEW', 'SCT'], true) => 'partly-cloudy-'.$time.'-fill',
            $layer['amount'] === 'BKN'                       => 'overcast-'.$time.'-fill',
            default                                          => 'overcast-fill',
        };
    }

    /**
     * Day or night at the observation time. Defaults to day without a position.
     */
    private function isDay(Metar $metar, ?float $lat, ?float $lon): bool
    {
        if ($lat === null || $lon === null) {
            return true;
        }

        $observed = is_string($metar->observed_date) ? strtotime($metar->observed_date) : false;
        $at = $observed === false ? time() : $observed;

        $today = date_sun_info($at, $lat, $lon);
        if ($today['sunrise'] === true) {
            return true;
        }

        if ($today['sunrise'] === false) {
            return false;
        }

        $events = [];
        foreach ([-86400, 0, 86400] as $offset) {
            $info = date_sun_info($at + $offset, $lat, $lon);
            foreach (['sunrise' => true, 'sunset' => false] as $key => $rising) {
                if (is_int($info[$key])) {
                    $events[$info[$key]] = $rising;
                }
            }
        }

        ksort($events);

        $isDay = true;
        foreach ($events as $time => $rising) {
            if ($time > $at) {
                break;
            }

            $isDay = $rising;
        }

        return $isDay;
    }
}
