<?php

declare(strict_types=1);

use App\Features\Flights\Actions\DescribeMetar;
use App\Support\Metar;

function describeMetar(string $raw, ?float $lat = null, ?float $lon = null): array
{
    $description = new DescribeMetar()->handle(new Metar($raw), $lat, $lon);

    return [$description->sentence, $description->icon, $description->category];
}

test('the ceiling is the lowest broken layer, not the lowest layer', function (): void {
    [$sentence, $icon, $category] = describeMetar('KJFK 282151Z 28026G39KT 10SM FEW055 SCT095 BKN110 BKN230 12/M04 A2958');

    expect($sentence)->toBe('Broken clouds at 11,000 ft, 12°C. Wind westerly at 26 knots, gusting 39 knots.')
        ->and($icon)->toBe('overcast-day-fill')
        ->and($category)->toBe('VFR');
});

test('without a ceiling the lowest layer is described', function (): void {
    [$sentence, $icon] = describeMetar('EGLL 281150Z 27009KT 9999 FEW045 14/08 Q1015');

    expect($sentence)->toBe('Few clouds at 4,500 ft, 14°C. Wind westerly at 9 knots.')
        ->and($icon)->toBe('partly-cloudy-day-fill');
});

test('CAVOK is clear skies and zero wind is calm', function (): void {
    [$sentence, $icon] = describeMetar('UKDR 281830Z 00000MPS CAVOK 08/07 Q1019');

    expect($sentence)->toBe('Clear skies, 8°C. Wind calm.')
        ->and($icon)->toBe('clear-day-fill');
});

test('variable wind has no compass direction', function (): void {
    [$sentence] = describeMetar('EDDF 281150Z VRB03KT 9999 SCT040 10/05 Q1020');

    expect($sentence)->toBe('Scattered clouds at 4,000 ft, 10°C. Wind variable at 3 knots.');
});

test('precipitation leads the sentence and outranks mist and sky in the icon', function (): void {
    [$sentence, $icon] = describeMetar('KTTN 281853Z 04011KT 1SM -RA BR OVC010 M02/M02 A3006');

    expect($sentence)->toStartWith('Light rain')
        ->and($sentence)->toContain('Overcast at 1,000 ft, -2°C.')
        ->and($icon)->toBe('rain-fill');
});

test('a thunderstorm outranks every other condition', function (): void {
    [$sentence, $icon] = describeMetar('KDFW 281853Z 18015G30KT 3SM +TSRA BKN020CB 24/22 A2990');

    expect($sentence)->toStartWith('Heavy thunderstorm with rain.')
        ->and($icon)->toBe('thunderstorms-day-fill');
});

test('the icon follows the sun at the station when a position is given', function (): void {
    $night = describeMetar('EGLL 280230Z 27009KT 9999 FEW045 14/08 Q1015', 51.47, -0.45);
    $day = describeMetar('EGLL 281200Z 27009KT 9999 FEW045 14/08 Q1015', 51.47, -0.45);

    expect($night[1])->toBe('partly-cloudy-night-fill')
        ->and($day[1])->toBe('partly-cloudy-day-fill');
});

test('a light rain report with a low ceiling reads the ceiling in feet', function (): void {
    [$sentence, $icon] = describeMetar('KJFK 060351Z 19015KT 1 1/2SM -RA BKN004 BKN020 OVC035 18/18 A2989 RMK AO2');

    expect($sentence)->toBe('Light rain. Broken clouds at 400 ft, 18°C. Wind southerly at 15 knots.')
        ->and($icon)->toBe('rain-fill');
});

test('a layer with an unreported height is never described as clear sky', function (): void {
    [$obscured, $obscuredIcon] = describeMetar('KJFK 060351Z 19015KT 1SM VV/// 18/18 A2989');
    [$broken, $brokenIcon] = describeMetar('KJFK 060351Z 19015KT 5SM BKN/// 18/18 A2989');

    expect($obscured)->toStartWith('Sky obscured, 18°C.')
        ->and($obscuredIcon)->toBe('overcast-fill')
        ->and($broken)->toStartWith('Broken clouds, 18°C.')
        ->and($brokenIcon)->toBe('overcast-day-fill');
});

test('a known ceiling wins over a layer with an unreported height', function (): void {
    [$sentence] = describeMetar('KJFK 060351Z 19015KT 5SM BKN/// OVC020 18/18 A2989');

    expect($sentence)->toStartWith('Overcast at 2,000 ft');
});
