<?php

declare(strict_types=1);

use App\Support\FlightTimeParser;

test('parser accepts documented formats', function (string $input, string $expected): void {
    expect(FlightTimeParser::parse($input))->toBe($expected);
})->with([
    'Hi format'             => ['0830', '08:30:00'],
    'H:i format'            => ['08:30', '08:30:00'],
    'G:i format'            => ['8:30', '08:30:00'],
    'h:i A format'          => ['08:30 AM', '08:30:00'],
    'h:i a format'          => ['08:30 am', '08:30:00'],
    'h:i A format PM'       => ['08:30 PM', '20:30:00'],
    'h:i a format pm'       => ['08:30 pm', '20:30:00'],
    'h A format'            => ['8 AM', '08:00:00'],
    'h a format'            => ['8 am', '08:00:00'],
    'h A format PM'         => ['8 PM', '20:00:00'],
    'h a format pm'         => ['8 pm', '20:00:00'],
    'G format single digit' => ['8', '08:00:00'],
    'H format two digits'   => ['08', '08:00:00'],
    'H format 23'           => ['23', '23:00:00'],
    'Z suffix stripped'     => ['0830Z', '08:30:00'],
    'L suffix stripped'     => ['0830L', '08:30:00'],
    'H:i with Z suffix'     => ['08:30Z', '08:30:00'],
    'h:i A with Z suffix'   => ['08:30 AMZ', '08:30:00'],
    'H:i:s format'          => ['08:30:00', '08:30:00'],
    'H:i:s midnight'        => ['00:00:00', '00:00:00'],
    'H:i:s end of day'      => ['23:59:59', '23:59:59'],
    'tz suffix CST'         => ['0810 CST', '08:10:00'],
    'tz suffix EST'         => ['1235 EST', '12:35:00'],
    'tz suffix UTC'         => ['08:30 UTC', '08:30:00'],
    'tz suffix with AM'     => ['8 AM PST', '08:00:00'],
    'tz suffix with PM'     => ['8 PM EST', '20:00:00'],
]);

test('parser returns null for empty or invalid input', function (?string $input): void {
    expect(FlightTimeParser::parse($input))->toBeNull();
})->with([
    'empty string' => [''],
    'null'         => [null],
    'TBD'          => ['TBD'],
    'invalid time' => ['2500'],
    'garbage'      => ['garbage'],
    '25:00'        => ['25:00'],
    'not a time'   => ['not a time'],
    '24:00:00'     => ['24:00:00'],
    '08:60:00'     => ['08:60:00'],
    '08:30:60'     => ['08:30:60'],
    'garbage CST'  => ['garbage CST'],
]);

test('parser is pure function with no I/O', function (): void {
    $result1 = FlightTimeParser::parse('0830');
    $result2 = FlightTimeParser::parse('0830');

    expect($result1)->toBe($result2)
        ->and($result1)->toBe('08:30:00');
});
